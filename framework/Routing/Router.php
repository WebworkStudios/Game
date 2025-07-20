<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Core\ServiceContainer;
use Framework\Http\HttpMethod;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;

/**
 * Router - PHP 8.4 Enhanced Route Handling
 */
final class Router
{
    // PHP 8.4: Typed Constants
    private const string DEFAULT_404_MESSAGE = 'Not Found';
    private const string DEFAULT_405_MESSAGE = 'Method Not Allowed';
    private const array ASSET_EXTENSIONS = [
        'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
        'ico', 'woff', 'woff2', 'ttf', 'eot', 'map'
    ];
    private const array ASSET_PATHS = [
        '/assets/', '/images/', '/js/', '/css/', '/fonts/',
        '/uploads/', '/static/', '/media/'
    ];
    private const int MAX_ROUTE_CACHE_SIZE = 1000;

    private array $routes = [];
    private array $namedRoutes = [];
    private bool $routesLoaded = false;
    private array $globalMiddlewares = [];
    private array $routeCache = [];

    // FIXED: Use Closure instead of callable for properties
    private ?\Closure $notFoundHandler = null;
    private ?\Closure $methodNotAllowedHandler = null;

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly RouterCache $cache
    ) {}

    /**
     * Handle HTTP Request - PHP 8.4 Enhanced
     */
    public function handle(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Fast asset handling
        if ($this->isAssetRequest($path)) {
            return $this->handleAssetRequest($request, $path);
        }

        $this->loadRoutes();

        // Try cache first for performance
        $cacheKey = $method->value . ':' . $path;
        if (isset($this->routeCache[$cacheKey])) {
            return $this->executeRoute($request, ...$this->routeCache[$cacheKey]);
        }

        $matchedRoute = $this->findRoute($path, $method);

        return match (true) {
            $matchedRoute === null => $this->handleNotFound($request),
            !$matchedRoute['route']->supportsMethod($method) =>
            $this->handleMethodNotAllowed($request, $matchedRoute['route']),
            default => $this->executeCachedRoute($request, $matchedRoute, $cacheKey)
        };
    }

    /**
     * Execute and Cache Route
     */
    private function executeCachedRoute(Request $request, array $matchedRoute, string $cacheKey): Response
    {
        $route = $matchedRoute['route'];
        $parameters = $matchedRoute['parameters'];

        // Cache route for future requests (with size limit)
        if (count($this->routeCache) < self::MAX_ROUTE_CACHE_SIZE) {
            $this->routeCache[$cacheKey] = [$route, $parameters];
        }

        return $this->executeRoute($request, $route, $parameters);
    }

    /**
     * Asset Request Detection - Performance Optimized
     */
    private function isAssetRequest(string $path): bool
    {
        // Quick path check first
        if (array_any(self::ASSET_PATHS, fn($assetPath) => str_starts_with($path, $assetPath))) {
            return true;
        }

        // Extension check
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return in_array(strtolower($extension), self::ASSET_EXTENSIONS, true);
    }

    /**
     * Handle Asset Request
     */
    private function handleAssetRequest(Request $request, string $path): Response
    {
        // Return 404 for assets (they should be served by web server)
        return new Response(
            HttpStatus::NOT_FOUND,
            ['Content-Type' => 'text/plain'],
            'Asset not found'
        );
    }

    /**
     * Load Routes - FIXED: Uses existing loadRouteEntries method
     */
    private function loadRoutes(): void
    {
        if ($this->routesLoaded) {
            return;
        }

        try {
            $routes = $this->cache->loadRouteEntries();

            foreach ($routes as $route) {
                $this->routes[] = $route;

                if ($route->name) {
                    $this->namedRoutes[$route->name] = $route;
                }
            }

            $this->routesLoaded = true;
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to load routes: " . $e->getMessage());
        }
    }

    /**
     * Find Route - PHP 8.4 Enhanced Matching
     */
    private function findRoute(string $path, HttpMethod $method): ?array
    {
        foreach ($this->routes as $route) {
            $parameters = $this->matchRoute($route, $path);

            if ($parameters !== null) {
                return [
                    'route' => $route,
                    'parameters' => $parameters
                ];
            }
        }

        return null;
    }

    /**
     * Match Single Route - FIXED: Uses existing matches method
     */
    private function matchRoute(RouteEntry $route, string $path): ?array
    {
        $matches = $route->matches($path);

        if ($matches !== false) {
            return $matches; // Returns array of named captures
        }

        return null;
    }

    /**
     * Handle Not Found - Enhanced
     */
    private function handleNotFound(Request $request): Response
    {
        if ($this->notFoundHandler) {
            $response = ($this->notFoundHandler)($request);
            if ($response instanceof Response) {
                return $response;
            }
        }

        return $this->createErrorResponse(
            HttpStatus::NOT_FOUND,
            self::DEFAULT_404_MESSAGE
        );
    }

    /**
     * Handle Method Not Allowed - FIXED: Uses existing methods property
     */
    private function handleMethodNotAllowed(Request $request, RouteEntry $route): Response
    {
        if ($this->methodNotAllowedHandler) {
            $response = ($this->methodNotAllowedHandler)($request, $route);
            if ($response instanceof Response) {
                return $response;
            }
        }

        $allowedMethods = implode(', ', array_map(
            fn($m) => $m->value,
            $route->methods
        ));

        return $this->createErrorResponse(
            HttpStatus::METHOD_NOT_ALLOWED,
            self::DEFAULT_405_MESSAGE,
            ['Allow' => $allowedMethods]
        );
    }

    /**
     * Execute Route - Enhanced Pipeline
     */
    private function executeRoute(Request $request, RouteEntry $route, array $parameters): Response
    {
        try {
            // Add path parameters to request
            $request = $request->withPathParameters($parameters);

            // Build and execute middleware pipeline
            $pipeline = $this->buildMiddlewarePipeline($route);
            return $pipeline($request);

        } catch (\Throwable $e) {
            error_log("Route execution error: " . $e->getMessage());

            return $this->createErrorResponse(
                HttpStatus::INTERNAL_SERVER_ERROR,
                'Internal Server Error'
            );
        }
    }

    /**
     * Build Middleware Pipeline - PHP 8.4 Enhanced
     */
    private function buildMiddlewarePipeline(RouteEntry $route): callable
    {
        $middlewares = [...$this->globalMiddlewares, ...$route->middlewares];

        return function (Request $request) use ($route, $middlewares) {
            $pipeline = $this->createPipeline($middlewares, function (Request $request) use ($route) {
                return $this->callAction($request, $route);
            });

            return $pipeline($request);
        };
    }

    /**
     * Create Middleware Pipeline
     */
    private function createPipeline(array $middlewares, callable $destination): callable
    {
        return array_reduce(
            array_reverse($middlewares),
            function (callable $next, string $middleware) {
                return function (Request $request) use ($middleware, $next) {
                    $middlewareInstance = $this->container->get($middleware);
                    return $middlewareInstance->handle($request, $next);
                };
            },
            $destination
        );
    }

    /**
     * Call Action - Enhanced Error Handling
     */
    private function callAction(Request $request, RouteEntry $route): Response
    {
        $actionClass = $route->action;

        if (!class_exists($actionClass)) {
            throw new \RuntimeException("Action class '{$actionClass}' not found");
        }

        $action = $this->container->get($actionClass);

        if (!is_callable($action)) {
            throw new \RuntimeException("Action '{$actionClass}' is not callable");
        }

        $response = $action($request);

        if (!$response instanceof Response) {
            throw new \RuntimeException("Action must return Response instance");
        }

        return $response;
    }

    /**
     * Create Error Response
     */
    private function createErrorResponse(HttpStatus $status, string $message, array $headers = []): Response
    {
        try {
            $responseFactory = $this->container->get(ResponseFactory::class);
            return $responseFactory->response($message, $status, $headers);
        } catch (\Throwable) {
            return new Response($status, $headers, $message);
        }
    }

    // Public API Methods - FIXED: Proper callable type handling

    public function addGlobalMiddleware(string $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    public function setNotFoundHandler(callable $handler): void
    {
        // FIXED: Convert callable to Closure for proper property typing
        $this->notFoundHandler = $handler instanceof \Closure ? $handler : \Closure::fromCallable($handler);
    }

    public function setMethodNotAllowedHandler(callable $handler): void
    {
        // FIXED: Convert callable to Closure for proper property typing
        $this->methodNotAllowedHandler = $handler instanceof \Closure ? $handler : \Closure::fromCallable($handler);
    }

    public function getNamedRoute(string $name): ?RouteEntry
    {
        $this->loadRoutes();
        return $this->namedRoutes[$name] ?? null;
    }

    public function url(string $name, array $parameters = []): string
    {
        $route = $this->getNamedRoute($name);

        if ($route === null) {
            throw new \InvalidArgumentException("Named route '{$name}' not found");
        }

        return $route->generateUrl($parameters);
    }

    public function getAllRoutes(): array
    {
        $this->loadRoutes();
        return $this->routes;
    }

    /**
     * Clear Route Cache - FIXED: Uses existing clear method
     */
    public function clearCache(): void
    {
        $this->routeCache = [];
        $this->cache->clear();
    }
}