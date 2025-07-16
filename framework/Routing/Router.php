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
 * KORRIGIERTE VERSION: Verwendet ResponseFactory statt statische Response-Methoden
 */
class Router
{
    private const string DEFAULT_404_MESSAGE = 'Not Found';
    private const string DEFAULT_405_MESSAGE = 'Method Not Allowed';

    private array $routes = [];
    private array $namedRoutes = [];
    private bool $routesLoaded = false;
    private array $globalMiddlewares = [];

    /** @var callable|null */
    private $notFoundHandler = null;

    /** @var callable|null */
    private $methodNotAllowedHandler = null;

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly RouterCache      $cache,
    )
    {
    }

    /**
     * Verarbeitet HTTP-Request und gibt Response zurück
     */
    public function handle(Request $request): Response
    {
        // IMMER zuerst Routes laden
        $this->loadRoutes();

        $method = $request->getMethod();
        $path = $request->getPath();

        $matchedRoute = $this->findRoute($path, $method);

        if ($matchedRoute === null) {
            return $this->handleNotFound($request);
        }

        if (!$matchedRoute['route']->supportsMethod($method)) {
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
            return;
        }

        $routes = $this->cache->loadRouteEntries();

        foreach ($routes as $route) {
            $this->routes[] = $route;

            if ($route->name !== null) {
                $this->namedRoutes[$route->name] = $route;
            }
        }

        $this->routesLoaded = true;
    }

    /**
     * Findet passende Route für Pfad und Methode
     */
    private function findRoute(string $path, HttpMethod $method): ?array
    {
        // WICHTIG: Routes MÜSSEN geladen werden BEVOR wir suchen
        $this->loadRoutes();

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
     *
     * FIX: Verwendet ResponseFactory statt statische Response-Methoden
     */
    private function handleNotFound(Request $request): Response
    {
        if ($this->notFoundHandler !== null) {
            $response = ($this->notFoundHandler)($request);

            if ($response instanceof Response) {
                return $response;
            }
        }

        // FIX: Verwende ResponseFactory statt Response::notFound()
        $responseFactory = $this->container->get(ResponseFactory::class);
        return $responseFactory->notFound(self::DEFAULT_404_MESSAGE);
    }

    /**
     * Behandelt 405 Method Not Allowed
     *
     * FIX: Verwendet ResponseFactory statt statische Response-Methoden
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

        // FIX: Verwende ResponseFactory statt Response::methodNotAllowed()
        $responseFactory = $this->container->get(ResponseFactory::class);
        return $responseFactory->methodNotAllowed(self::DEFAULT_405_MESSAGE)
            ->withHeader('Allow', implode(', ', $allowedMethods));
    }

    /**
     * Führt Route mit Middleware-Chain aus
     */
    private function executeRoute(Request $request, RouteEntry $route, array $parameters): Response
    {
        // Parameter zum Request hinzufügen
        $requestWithParams = $this->addParametersToRequest($request, $parameters);

        // Middleware-Chain aufbauen: Global + Route Middlewares
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
     * Fügt Route-Parameter zum Request hinzu
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
        $action = $this->container->get($actionClass);

        if (!method_exists($action, '__invoke')) {
            throw new RuntimeException("Action {$actionClass} must have __invoke method");
        }

        $response = $action($request);

        if (!$response instanceof Response) {
            throw new RuntimeException(
                "Action {$actionClass} must return Response instance"
            );
        }

        return $response;
    }

    /**
     * Fügt globale Middleware hinzu
     */
    public function addGlobalMiddleware(string $middlewareClass): void
    {
        $this->globalMiddlewares[] = $middlewareClass;
    }

    /**
     * Holt alle globalen Middlewares
     */
    public function getGlobalMiddlewares(): array
    {
        return $this->globalMiddlewares;
    }

    /**
     * Setzt alle globalen Middlewares
     */
    public function setGlobalMiddlewares(array $middlewares): void
    {
        $this->globalMiddlewares = $middlewares;
    }

    /**
     * Setzt 404-Handler
     */
    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Setzt 405-Handler
     */
    public function setMethodNotAllowedHandler(callable $handler): void
    {
        $this->methodNotAllowedHandler = $handler;
    }

    /**
     * Generiert URL für benannte Route
     */
    public function url(string $name, array $parameters = []): string
    {
        $this->loadRoutes();

        if (!isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException("Route '{$name}' not found");
        }

        $route = $this->namedRoutes[$name];
        return $route->buildUrl($parameters);
    }

    /**
     * Holt benannte Route
     */
    public function getNamedRoute(string $name): ?RouteEntry
    {
        $this->loadRoutes();
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Holt alle Routes
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