<?php

declare(strict_types=1);

namespace Framework\Routing;

use Exception;
use Framework\Core\ServiceContainer;
use Framework\Http\HttpMethod;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Router - KORRIGIERT: Nutzt nur existierende RouteEntry-Methoden
 */
class Router
{
    private const string DEFAULT_404_MESSAGE = 'Not Found';
    private const string DEFAULT_405_MESSAGE = 'Method Not Allowed';

    private array $routes = [];
    private array $namedRoutes = [];
    private bool $routesLoaded = false;
    private array $globalMiddlewares = [];

    // Asset-Pfade die nicht durch den Router geleitet werden sollen
    private const array ASSET_EXTENSIONS = [
        'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'eot'
    ];

    private const array ASSET_PATHS = [
        '/assets/', '/images/', '/js/', '/css/', '/fonts/', '/uploads/', '/static/'
    ];

    private mixed $notFoundHandler = null;
    private mixed $methodNotAllowedHandler = null;

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly RouterCache $cache,
    ) {}

    public function handle(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // KRITISCH: Asset-Prüfung VOR Route-Loading
        if ($this->isAssetRequest($path)) {
            return $this->handleAssetRequest($request, $path);
        }

        $this->loadRoutes();

        $matchedRoute = $this->findRoute($path, $method);

        // KRITISCH: Null-Check vor Zugriff auf Route
        if ($matchedRoute === null) {
            return $this->handleNotFound($request);
        }

        // KORRIGIERT: Nutze existierende supportsMethod aus RouteEntry
        $route = $matchedRoute['route'];
        if (!$route->supportsMethod($method)) {
            return $this->handleMethodNotAllowed($request, $route);
        }

        return $this->executeRoute($request, $route, $matchedRoute['parameters']);
    }

    /**
     * Asset-Request-Erkennung
     */
    private function isAssetRequest(string $path): bool
    {
        // 1. Check Asset-Pfade
        foreach (self::ASSET_PATHS as $assetPath) {
            if (str_starts_with($path, $assetPath)) {
                return true;
            }
        }

        // 2. Check Datei-Extensions
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, self::ASSET_EXTENSIONS, true)) {
            return true;
        }

        return false;
    }

    /**
     * Asset-Request-Behandlung
     */
    private function handleAssetRequest(Request $request, string $path): Response
    {
        // Einfache 404 für Assets - keine aufwendige Template-Behandlung
        error_log("Asset not found: {$path}");
        return new Response(
            HttpStatus::NOT_FOUND,
            ['Content-Type' => 'text/plain'],
            "Asset not found: {$path}"
        );
    }

    /**
     * Routes laden
     */
    private function loadRoutes(): void
    {
        if ($this->routesLoaded) {
            return;
        }

        try {
            // KORRIGIERT: Nutze verfügbare RouterCache-Methode
            $routes = $this->cache->loadRouteEntries();
            $this->routes = $routes;
            $this->buildNamedRoutes();
            $this->routesLoaded = true;
        } catch (\Throwable $e) {
            error_log("Route loading error: " . $e->getMessage());
            $this->routes = [];
            $this->routesLoaded = true;
        }
    }

    private function buildNamedRoutes(): void
    {
        $this->namedRoutes = [];

        foreach ($this->routes as $route) {
            // KORRIGIERT: Nutze verfügbare RouteEntry-Properties
            if ($route->name !== null) {
                $this->namedRoutes[$route->name] = $route;
            }
        }
    }

    /**
     * KORRIGIERT: findRoute nutzt verfügbare RouteEntry::matches()
     */
    private function findRoute(string $path, HttpMethod $method): ?array
    {
        if (empty($this->routes)) {
            return null;
        }

        foreach ($this->routes as $route) {
            if ($route === null) {
                continue;
            }

            try {
                // KORRIGIERT: Nutze verfügbare matches() Methode
                $parameters = $route->matches($path);

                if ($parameters !== false) {
                    return [
                        'route' => $route,
                        'parameters' => $parameters,
                    ];
                }
            } catch (\Throwable $e) {
                error_log("Route matching error for path '{$path}': " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    /**
     * 404 Handler
     */
    private function handleNotFound(Request $request): Response
    {
        if ($this->notFoundHandler !== null) {
            try {
                $response = ($this->notFoundHandler)($request);
                if ($response instanceof Response) {
                    return $response;
                }
            } catch (\Throwable $e) {
                error_log("404 handler error: " . $e->getMessage());
            }
        }

        try {
            $responseFactory = $this->container->get(ResponseFactory::class);
            return $responseFactory->response(self::DEFAULT_404_MESSAGE, HttpStatus::NOT_FOUND);
        } catch (\Throwable $e) {
            return new Response(
                HttpStatus::NOT_FOUND,
                ['Content-Type' => 'text/plain'],
                self::DEFAULT_404_MESSAGE
            );
        }
    }

    /**
     * KORRIGIERT: 405 Handler nutzt verfügbare RouteEntry-Properties
     */
    private function handleMethodNotAllowed(Request $request, RouteEntry $route): Response
    {
        if ($this->methodNotAllowedHandler !== null) {
            try {
                $response = ($this->methodNotAllowedHandler)($request, $route);
                if ($response instanceof Response) {
                    return $response;
                }
            } catch (\Throwable $e) {
                error_log("405 handler error: " . $e->getMessage());
            }
        }

        try {
            $responseFactory = $this->container->get(ResponseFactory::class);

            // KORRIGIERT: Nutze verfügbare RouteEntry::methods Property
            $allowedMethods = implode(', ', array_map(fn($m) => $m->value, $route->methods));

            return $responseFactory->response(
                self::DEFAULT_405_MESSAGE,
                HttpStatus::METHOD_NOT_ALLOWED,
                ['Allow' => $allowedMethods]
            );
        } catch (\Throwable $e) {
            return new Response(
                HttpStatus::METHOD_NOT_ALLOWED,
                ['Content-Type' => 'text/plain'],
                self::DEFAULT_405_MESSAGE
            );
        }
    }

    /**
     * KORRIGIERT: Route ausführen mit verfügbaren Properties
     */
    private function executeRoute(Request $request, RouteEntry $route, array $parameters): Response
    {
        try {
            // Add path parameters to request
            $request = $request->withPathParameters($parameters);

            // KORRIGIERT: Nutze verfügbare RouteEntry::middlewares Property
            $pipeline = $this->buildMiddlewarePipeline($route);

            return $pipeline($request);
        } catch (\Throwable $e) {
            error_log("Route execution error: " . $e->getMessage());

            try {
                $responseFactory = $this->container->get(ResponseFactory::class);
                return $responseFactory->response(
                    'Internal Server Error',
                    HttpStatus::INTERNAL_SERVER_ERROR
                );
            } catch (\Throwable $fallbackError) {
                return new Response(
                    HttpStatus::INTERNAL_SERVER_ERROR,
                    ['Content-Type' => 'text/plain'],
                    'Internal Server Error'
                );
            }
        }
    }

    /**
     * KORRIGIERT: Middleware-Pipeline mit verfügbaren Properties
     */
    private function buildMiddlewarePipeline(RouteEntry $route): callable
    {
        // KORRIGIERT: Nutze verfügbare RouteEntry::middlewares Property
        $middlewares = [...$this->globalMiddlewares, ...$route->middlewares];

        return function (Request $request) use ($route, $middlewares) {
            $pipeline = $this->createPipeline($middlewares, function (Request $request) use ($route) {
                return $this->callAction($request, $route);
            });

            return $pipeline($request);
        };
    }

    /**
     * Pipeline erstellen
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
     * KORRIGIERT: Action aufrufen mit verfügbarer RouteEntry::action Property
     */
    private function callAction(Request $request, RouteEntry $route): Response
    {
        // KORRIGIERT: Nutze verfügbare RouteEntry::action Property
        $actionClass = $route->action;

        if (!class_exists($actionClass)) {
            throw new RuntimeException("Action class '{$actionClass}' not found");
        }

        $action = $this->container->get($actionClass);

        if (!is_callable($action)) {
            throw new RuntimeException("Action '{$actionClass}' is not callable");
        }

        $response = $action($request);

        if (!$response instanceof Response) {
            throw new RuntimeException("Action must return Response instance");
        }

        return $response;
    }

    // Public API Methods

    public function addGlobalMiddleware(string $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function setMethodNotAllowedHandler(callable $handler): void
    {
        $this->methodNotAllowedHandler = $handler;
    }

    /**
     * KORRIGIERT: Named Route mit verfügbaren Properties
     */
    public function getNamedRoute(string $name): ?RouteEntry
    {
        $this->loadRoutes();
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * KORRIGIERT: URL-Generierung mit verfügbarer generateUrl() Methode
     */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->getNamedRoute($name);

        if ($route === null) {
            throw new InvalidArgumentException("Named route '{$name}' not found");
        }

        // KORRIGIERT: Nutze verfügbare RouteEntry::generateUrl() Methode
        return $route->generateUrl($parameters);
    }

    public function getAllRoutes(): array
    {
        $this->loadRoutes();
        return $this->routes;
    }
}