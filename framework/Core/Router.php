<?php

/**
 * Router with Attribute-based Routing
 * Automatic route discovery using PHP 8.4 attributes
 *
 * File: framework/Core/Router.php
 * Directory: /framework/Core/
 */

declare(strict_types=1);

namespace Framework\Core;

use FilesystemIterator;
use Framework\Core\Attributes\Route;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use RegexIterator;
use Throwable;

class Router
{
    /** @var array<string, array> */
    private array $routes = [];

    /** @var array<string, mixed> */
    private array $namedRoutes = [];

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->discoverRoutes();
    }

    /**
     * Discover routes from Action classes using attributes
     */
    private function discoverRoutes(): void
    {
        $srcPath = __DIR__ . '/../../src';

        if (!is_dir($srcPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcPath, FilesystemIterator::SKIP_DOTS)
        );

        $files = new RegexIterator($iterator, '/^.+Action\.php$/i', RegexIterator::GET_MATCH);

        foreach ($files as $file) {
            $filePath = $file[0];
            $className = $this->getClassNameFromFile($filePath);

            if ($className && class_exists($className)) {
                $this->registerActionRoutes($className);
            }
        }
    }

    /**
     * Get class name from file path
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if (!$content) {
            return null;
        }

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            $namespace = $namespaceMatch[1];
        } else {
            $namespace = '';
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            $className = $classMatch[1];
            return $namespace ? $namespace . '\\' . $className : $className;
        }

        return null;
    }

    /**
     * Register routes from Action class attributes
     */
    private function registerActionRoutes(string $className): void
    {
        try {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(Route::class);

            foreach ($attributes as $attribute) {
                $route = $attribute->newInstance();

                $this->routes[$route->method][] = [
                    'pattern' => $route->path,
                    'action' => $className,
                    'name' => $route->name,
                    'middleware' => $route->middleware,
                    'rateLimit' => $route->rateLimit
                ];

                if ($route->name) {
                    $this->namedRoutes[$route->name] = [
                        'pattern' => $route->path,
                        'method' => $route->method
                    ];
                }
            }
        } catch (ReflectionException $e) {
            // Log error but continue
            error_log("Error registering routes for $className: " . $e->getMessage());
        }
    }

    /**
     * Handle incoming request
     * @throws ReflectionException
     */
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Remove trailing slash except for root
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        // Find matching route
        $route = $this->findRoute($method, $uri);

        if (!$route) {
            $this->handleNotFound();
            return;
        }

        // Apply rate limiting
        if ($route['rateLimit']) {
            $rateLimiter = $this->container->get('rateLimiter');

            if (!$rateLimiter->allowRequest($route['rateLimit'])) {
                $this->handleRateLimit();
                return;
            }
        }

        // Apply middleware
        foreach ($route['middleware'] as $middleware) {
            $middlewareInstance = $this->container->get($middleware);

            if (!$middlewareInstance->handle()) {
                return;
            }
        }

        // Execute action
        $this->executeAction($route['action'], $route['params'] ?? []);
    }

    /**
     * Find matching route
     */
    private function findRoute(string $method, string $uri): ?array
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $route) {
            $pattern = $route['pattern'];
            $params = [];

            // Convert route pattern to regex
            $regex = preg_replace_callback(
                '/\{([^}]+)}/',
                function ($matches) use (&$params) {
                    $param = $matches[1];
                    $params[] = $param;

                    // Handle optional parameters
                    if (str_contains($param, '?')) {
                        return '([^/]*)?';
                    }

                    return '([^/]+)';
                },
                $pattern
            );

            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches); // Remove full match

                $paramValues = [];
                foreach ($params as $index => $param) {
                    $paramName = str_replace('?', '', $param);
                    $paramValues[$paramName] = $matches[$index] ?? null;
                }

                return [
                    'action' => $route['action'],
                    'params' => $paramValues,
                    'middleware' => $route['middleware'],
                    'rateLimit' => $route['rateLimit']
                ];
            }
        }

        return null;
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        echo "404 - Page Not Found";
    }

    /**
     * Handle rate limit exceeded
     */
    private function handleRateLimit(): void
    {
        http_response_code(429);
        echo "429 - Too Many Requests";
    }

    /**
     * Execute action
     */
    private function executeAction(string $actionClass, array $params): void
    {
        try {
            $action = $this->container->get($actionClass);

            // Call __invoke method with parameters
            $this->container->call($action, '__invoke', $params);

        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Handle errors
     */
    private function handleError(Throwable $e): void
    {
        $logger = $this->container->get('logger');
        $logger->error('Action execution error: ' . $e->getMessage(), [
            'exception' => $e,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        http_response_code(500);
        echo "500 - Internal Server Error";
    }

    /**
     * Generate URL for named route
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException("Named route [$name] not found");
        }

        $route = $this->namedRoutes[$name];
        $url = $route['pattern'];

        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', (string)$value, $url);
            $url = str_replace('{' . $key . '?}', (string)$value, $url);
        }

        // Remove unused optional parameters
        return preg_replace('/\{[^}]+\?}/', '', $url);
    }
}