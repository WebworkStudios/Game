<?php
/**
 * Router with Attribute-based Routing - Complete Fixed Version
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
     * Get logger instance (lazy loaded)
     */
    private function getLogger(): ?object
    {
        try {
            return $this->container->has('logger') ? $this->container->get('logger') : null;
        } catch (Throwable) {
            return null;
        }
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
        } catch (ReflectionException) {
            // Log error but continue
            error_log("Error registering routes for $className");
        }
    }

    /**
     * Handle incoming request
     */
    public function handle(): void
    {
        try {
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
                if (!$this->container->has('rateLimiter')) {
                    $this->getLogger()?->warning('Rate limiter not configured but required for route');
                } else {
                    $rateLimiter = $this->container->get('rateLimiter');
                    if (!$rateLimiter->allowRequest($route['rateLimit'])) {
                        $this->handleRateLimit();
                        return;
                    }
                }
            }

            // Apply middleware
            foreach ($route['middleware'] as $middleware) {
                if (!$this->container->has($middleware)) {
                    $this->getLogger()?->error("Middleware [{$middleware}] not found");
                    $this->handleError(new \Exception("Middleware not found: {$middleware}"));
                    return;
                }

                $middlewareInstance = $this->container->get($middleware);

                if (!method_exists($middlewareInstance, 'handle') || !$middlewareInstance->handle()) {
                    return;
                }
            }

            // Execute action
            $this->executeAction($route['action'], $route['params'] ?? []);

        } catch (Throwable $e) {
            $this->handleError($e);
        }
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

        $this->getLogger()?->info('404 Not Found', [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        if ($this->isJsonRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Not Found',
                'message' => 'The requested resource was not found',
                'status' => 404
            ]);
        } else {
            echo $this->render404Page();
        }
    }

    /**
     * Handle rate limit exceeded
     */
    private function handleRateLimit(): void
    {
        http_response_code(429);

        $this->getLogger()?->warning('Rate limit exceeded', [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        if ($this->isJsonRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'status' => 429
            ]);
        } else {
            echo "429 - Too Many Requests. Please try again later.";
        }
    }

    /**
     * Handle errors
     */
    private function handleError(Throwable $e): void
    {
        $this->getLogger()?->error('Router error: ' . $e->getMessage(), [
            'exception' => $e,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        http_response_code(500);

        if ($this->isJsonRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Internal Server Error',
                'message' => 'An unexpected error occurred',
                'status' => 500
            ]);
        } else {
            if (($_ENV['APP_DEBUG'] ?? false)) {
                echo "<pre>Error: " . htmlspecialchars($e->getMessage()) . "\n\n";
                echo "File: " . htmlspecialchars($e->getFile()) . "\n";
                echo "Line: " . $e->getLine() . "\n\n";
                echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            } else {
                echo "500 - Internal Server Error";
            }
        }
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
     * Check if request expects JSON response
     */
    private function isJsonRequest(): bool
    {
        $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return str_contains($contentType, 'application/json') ||
            str_contains($accept, 'application/json');
    }

    /**
     * Render 404 page
     */
    private function render404Page(): string
    {
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found - Football Manager</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            text-align: center; 
            padding: 50px;
            background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 100%);
            color: white;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            max-width: 600px; 
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        h1 { 
            color: white; 
            font-size: 4rem; 
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        p { 
            font-size: 1.2rem; 
            margin-bottom: 30px; 
            opacity: 0.9;
        }
        .btn { 
            background: rgba(255,255,255,0.2); 
            color: white; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 25px;
            border: 2px solid rgba(255,255,255,0.3);
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn:hover { 
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚽ 404</h1>
        <p>The page you\'re looking for doesn\'t exist.</p>
        <a href="/" class="btn">🏠 Go Home</a>
    </div>
</body>
</html>';
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

    /**
     * Add a route manually
     */
    public function addRoute(string $method, string $path, string $action, ?string $name = null, array $middleware = [], ?int $rateLimit = null): void
    {
        $this->routes[$method][] = [
            'pattern' => $path,
            'action' => $action,
            'name' => $name,
            'middleware' => $middleware,
            'rateLimit' => $rateLimit
        ];

        if ($name) {
            $this->namedRoutes[$name] = [
                'pattern' => $path,
                'method' => $method
            ];
        }
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get all named routes
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    /**
     * Check if route exists
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Get route information
     */
    public function getRoute(string $name): ?array
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Clear all routes (useful for testing)
     */
    public function clearRoutes(): void
    {
        $this->routes = [];
        $this->namedRoutes = [];
    }

    /**
     * Group routes with common attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousRoutes = $this->routes;
        $previousNamedRoutes = $this->namedRoutes;

        // Execute callback to register routes
        $callback($this);

        // Apply group attributes to newly added routes
        $prefix = $attributes['prefix'] ?? '';
        $middleware = $attributes['middleware'] ?? [];
        $namespace = $attributes['namespace'] ?? '';

        foreach ($this->routes as $method => $routes) {
            if (!isset($previousRoutes[$method])) {
                $previousRoutes[$method] = [];
            }

            $newRoutes = array_slice($routes, count($previousRoutes[$method]));

            foreach ($newRoutes as &$route) {
                // Add prefix to pattern
                if ($prefix) {
                    $route['pattern'] = rtrim($prefix, '/') . '/' . ltrim($route['pattern'], '/');
                }

                // Add group middleware
                $route['middleware'] = array_merge($middleware, $route['middleware']);

                // Add namespace to action
                if ($namespace) {
                    $route['action'] = trim($namespace, '\\') . '\\' . $route['action'];
                }
            }

            $this->routes[$method] = array_merge($previousRoutes[$method], $newRoutes);
        }
    }
}