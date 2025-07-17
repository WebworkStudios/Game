<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Core\ServiceContainer;
use Framework\Http\HttpMethod;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use InvalidArgumentException;
use RuntimeException;

/**
 * Router - Attribute-based HTTP Router mit Middleware-Support
 *
 * KORRIGIERTE VERSION: Alle Probleme behoben
 */
class Router
{
    private const string DEFAULT_404_MESSAGE = 'Not Found';
    private const string DEFAULT_405_MESSAGE = 'Method Not Allowed';

    private array $routes = [];
    private array $namedRoutes = [];
    private bool $routesLoaded = false;
    private array $globalMiddlewares = [];

    // KORRIGIERT: Callable properties mit korrekter Syntax
    private mixed $notFoundHandler = null;
    private mixed $methodNotAllowedHandler = null;

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly RouterCache      $cache,
    )
    {
    }

    public function handle(Request $request): Response
    {
        $this->loadRoutes();

        $method = $request->getMethod();
        $path = $request->getPath();

        // DEBUG: Ausgabe der aktuellen Route-Anfrage
        error_log("=== ROUTER DEBUG ===");
        error_log("Requested method: " . $method->value);
        error_log("Requested path: " . $path);
        error_log("Routes loaded: " . ($this->routesLoaded ? 'YES' : 'NO'));
        error_log("Total routes: " . count($this->routes));

        // DEBUG: Alle verfügbaren Routes anzeigen
        if (!empty($this->routes)) {
            error_log("Available routes:");
            foreach ($this->routes as $route) {
                error_log("  - " . $route->pattern . " [" . implode(',', array_map(fn($m) => $m->value, $route->methods)) . "] -> " . $route->action);
            }
        } else {
            error_log("NO ROUTES FOUND!");
        }

        // DEBUG: Named routes
        if (!empty($this->namedRoutes)) {
            error_log("Named routes:");
            foreach ($this->namedRoutes as $name => $route) {
                error_log("  - {$name}: " . $route->pattern . " -> " . $route->action);
            }
        }

        $matchedRoute = $this->findRoute($path, $method);

        // DEBUG: Route-Matching Ergebnis
        if ($matchedRoute === null) {
            error_log("NO ROUTE MATCHED for path: " . $path);
            return $this->handleNotFound($request);
        } else {
            error_log("ROUTE MATCHED: " . $matchedRoute['route']->action);
            error_log("Parameters: " . json_encode($matchedRoute['parameters']));
        }

        if (!$matchedRoute['route']->supportsMethod($method)) {
            error_log("METHOD NOT ALLOWED: " . $method->value);
            return $this->handleMethodNotAllowed($request, $matchedRoute['route']);
        }

        return $this->executeRoute($request, $matchedRoute['route'], $matchedRoute['parameters']);
    }

    /**
     * Lädt alle Routes
     */
    private function loadRoutes(): void
    {
        if ($this->routesLoaded) {
            error_log("Routes already loaded, skipping...");
            return;
        }

        error_log("=== LOADING ROUTES ===");
        error_log("Cache file: " . $this->cache->getCacheFile());
        error_log("Actions path: " . $this->cache->getActionsPath());
        error_log("Cache exists: " . ($this->cache->exists() ? 'YES' : 'NO'));

        // Zeige Cache-Debug-Informationen
        $cacheDebug = $this->cache->debug();
        error_log("Cache debug info: " . json_encode($cacheDebug, JSON_PRETTY_PRINT));

        // Lösche Cache für frische Generierung
        error_log("Clearing cache for fresh build...");
        $this->cache->clear();

        // Lade Routes über Cache
        $routes = $this->cache->loadRouteEntries();
        error_log("Routes loaded from cache: " . count($routes));

        if (!empty($routes)) {
            foreach ($routes as $route) {
                error_log("Loaded route: " . $route->pattern . " [" .
                    implode(',', array_map(fn($m) => $m->value, $route->methods)) . "] -> " . $route->action);
            }
        }

        $this->routes = $routes;
        $this->buildNamedRoutes();

        error_log("Named routes built: " . count($this->namedRoutes));

        $this->routesLoaded = true;
    }

    private function buildNamedRoutes(): void
    {
        error_log("=== BUILDING NAMED ROUTES ===");

        $this->namedRoutes = [];

        foreach ($this->routes as $route) {
            if ($route->name !== null) {
                error_log("Named route: " . $route->name . " -> " . $route->pattern);
                $this->namedRoutes[$route->name] = $route;
            }
        }

        error_log("Total named routes: " . count($this->namedRoutes));
    }

    /**
     * Findet passende Route für Pfad und Methode
     */
    private function findRoute(string $path, HttpMethod $method): ?array
    {
        foreach ($this->routes as $route) {
            $parameters = $route->matches($path);

            if ($parameters !== false) {
                return [
                    'route' => $route,
                    'parameters' => $parameters,
                ];
            }
        }

        return null;
    }

    /**
     * Behandelt 404 Not Found
     */
    private function handleNotFound(Request $request): Response
    {
        if ($this->notFoundHandler !== null) {
            $response = ($this->notFoundHandler)($request);

            if ($response instanceof Response) {
                return $response;
            }
        }

        $responseFactory = $this->container->get(ResponseFactory::class);
        return $responseFactory->notFound(self::DEFAULT_404_MESSAGE);
    }

    /**
     * Behandelt 405 Method Not Allowed
     */
    private function handleMethodNotAllowed(Request $request, RouteEntry $route): Response
    {
        if ($this->methodNotAllowedHandler !== null) {
            $response = ($this->methodNotAllowedHandler)($request, $route);

            if ($response instanceof Response) {
                return $response;
            }
        }

        $allowedMethods = array_map(
            fn(HttpMethod $method) => $method->value,
            $route->methods
        );

        $responseFactory = $this->container->get(ResponseFactory::class);
        return $responseFactory->methodNotAllowed(self::DEFAULT_405_MESSAGE)
            ->withHeader('Allow', implode(', ', $allowedMethods));
    }

    /**
     * Führt Route mit Middleware-Chain aus
     */
    private function executeRoute(Request $request, RouteEntry $route, array $parameters): Response
    {
        // KORRIGIERT: Verwende erweiterten Request mit Parametern
        $requestWithParams = $this->addParametersToRequest($request, $parameters);

        // Middleware-Chain aufbauen
        $middlewares = array_merge($this->globalMiddlewares, $route->middlewares);
        $handler = fn(Request $req) => $this->executeAction($req, $route->action);

        // Middleware-Chain rückwärts aufbauen
        foreach (array_reverse($middlewares) as $middlewareClass) {
            $currentHandler = $handler;
            $handler = function (Request $req) use ($middlewareClass, $currentHandler) {
                $middleware = $this->container->get($middlewareClass);

                if (!$middleware instanceof MiddlewareInterface) {
                    throw new RuntimeException(
                        "Middleware {$middlewareClass} must implement MiddlewareInterface"
                    );
                }

                return $middleware->handle($req, $currentHandler);
            };
        }

        return $handler($requestWithParams);
    }

    /**
     * KORRIGIERT: Fügt Route-Parameter zum Request hinzu
     */
    private function addParametersToRequest(Request $request, array $parameters): Request
    {
        if (empty($parameters)) {
            return $request;
        }

        // Parameter zu Query-Parametern hinzufügen (für einfachen Zugriff)
        $query = array_merge($request->getQuery(), $parameters);

        return new Request(
            method: $request->getMethod(),
            uri: $request->getUri(),
            headers: $request->getHeaders(),
            query: $query,
            post: $request->getPost(),
            files: $request->getFiles(),
            cookies: $request->getCookies(),
            server: $request->getServer(),
            body: $request->getBody(),
            protocol: $request->getProtocol(),
        );
    }

    /**
     * Führt Action aus
     */
    private function executeAction(Request $request, string $actionClass): Response
    {
        try {
            $action = $this->container->get($actionClass);

            if (!is_callable($action)) {
                throw new InvalidArgumentException(
                    "Action {$actionClass} must be callable"
                );
            }

            $response = $action($request);

            if (!$response instanceof Response) {
                throw new InvalidArgumentException(
                    'Action must return a Response instance. Got: ' . get_debug_type($response)
                );
            }

            return $response;

        } catch (\Throwable $e) {
            return $this->handleActionError($e, $request);
        }
    }

    /**
     * Behandelt Action-Fehler
     */
    private function handleActionError(\Throwable $e, Request $request): Response
    {
        $responseFactory = $this->container->get(ResponseFactory::class);

        // In Development: Detaillierte Fehlermeldung
        if ($this->isDebugMode()) {
            $errorDetails = [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];

            if ($request->expectsJson()) {
                return $responseFactory->json($errorDetails, \Framework\Http\HttpStatus::INTERNAL_SERVER_ERROR);
            } else {
                return $responseFactory->serverError(
                    $this->formatErrorForHtml($errorDetails)
                );
            }
        }

        // Production: Generische Fehlermeldung
        if ($request->expectsJson()) {
            return $responseFactory->json(
                ['error' => 'Internal server error'],
                \Framework\Http\HttpStatus::INTERNAL_SERVER_ERROR
            );
        }

        return $responseFactory->serverError('Internal Server Error');
    }

    /**
     * Prüft ob Debug-Modus aktiv ist
     */
    private function isDebugMode(): bool
    {
        try {
            $config = $this->container->get('config');
            return $config['app']['debug'] ?? false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Formatiert Fehler für HTML-Ausgabe
     */
    private function formatErrorForHtml(array $errorDetails): string
    {
        return sprintf(
            '<h1>Application Error</h1><p><strong>%s</strong></p><p>File: %s:%d</p><pre>%s</pre>',
            htmlspecialchars($errorDetails['error']),
            htmlspecialchars($errorDetails['file']),
            $errorDetails['line'],
            htmlspecialchars($errorDetails['trace'])
        );
    }

    // ===================================================================
    // Public API Methods
    // ===================================================================

    /**
     * Registriert globale Middleware
     */
    public function addGlobalMiddleware(string $middlewareClass): void
    {
        $this->globalMiddlewares[] = $middlewareClass;
    }

    /**
     * Registriert benutzerdefinierten 404 Handler
     */
    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Registriert benutzerdefinierten 405 Handler
     */
    public function setMethodNotAllowedHandler(callable $handler): void
    {
        $this->methodNotAllowedHandler = $handler;
    }

    /**
     * KORRIGIERT: Generiert URL für benannte Route
     */
    public function route(string $name, array $parameters = []): string
    {
        $this->loadRoutes();

        if (!isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException("Route '{$name}' not found");
        }

        $route = $this->namedRoutes[$name];
        return $this->generateUrl($route, $parameters);
    }

    /**
     * KORRIGIERT: Generiert URL aus RouteEntry
     */
    private function generateUrl(RouteEntry $route, array $parameters = []): string
    {
        // Hole die Route-Attribute für Pattern-Generierung
        $routeAttributes = $this->getRouteAttributes($route->action);

        if (empty($routeAttributes)) {
            throw new RuntimeException("No route attributes found for action {$route->action}");
        }

        // Verwende das erste Route-Attribut
        $routeAttribute = $routeAttributes[0];
        $path = $routeAttribute->path;

        // Ersetze Parameter im Pfad
        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }

        return $path;
    }

    /**
     * Holt Route-Attribute einer Action-Klasse
     */
    private function getRouteAttributes(string $actionClass): array
    {
        try {
            $reflection = new \ReflectionClass($actionClass);
            $attributes = $reflection->getAttributes(Route::class);

            return array_map(
                fn(\ReflectionAttribute $attr) => $attr->newInstance(),
                $attributes
            );
        } catch (\ReflectionException) {
            return [];
        }
    }

    /**
     * Prüft ob Route existiert
     */
    public function hasRoute(string $name): bool
    {
        $this->loadRoutes();
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Holt alle registrierten Routes
     */
    public function getRoutes(): array
    {
        $this->loadRoutes();
        return $this->routes;
    }

    /**
     * Holt alle benannten Routes
     */
    public function getNamedRoutes(): array
    {
        $this->loadRoutes();
        return $this->namedRoutes;
    }
}