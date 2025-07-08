<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Core\ServiceContainer;
use Framework\Http\HttpMethod;
use Framework\Http\Request;
use Framework\Http\Response;
use InvalidArgumentException;
use RuntimeException;

/**
 * Router - Attribute-based HTTP Router mit Middleware-Support
 */
class Router
{
    private const string DEFAULT_404_MESSAGE = 'Not Found';
    private const string DEFAULT_405_MESSAGE = 'Method Not Allowed';

    private array $routes = [];
    private array $namedRoutes = [];
    private bool $routesLoaded = false;

    /** @var callable|null */
    private $notFoundHandler = null;

    /** @var callable|null */
    private $methodNotAllowedHandler = null;

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly RouterCache $cache,
    ) {}

    /**
     * Verarbeitet HTTP-Request und gibt Response zurück
     */
    public function handle(Request $request): Response
    {
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
        $pattern = $route->pattern;

        // Entferne Regex-Anchors
        $url = trim($pattern, '#^$');

        // Ersetze Parameter
        foreach ($parameters as $key => $value) {
            $url = preg_replace("/\(\?\P<{$key}>[^)]+\)/", (string) $value, $url);
        }

        // Prüfe ob alle Parameter ersetzt wurden
        if (preg_match('/\(\?\?P<\w+>[^)]+\)/', $url)) {
            throw new InvalidArgumentException("Missing parameters for route '{$name}'");
        }

        return $url;
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
     * Führt Route mit Middleware-Chain aus
     */
    private function executeRoute(Request $request, RouteEntry $route, array $parameters): Response
    {
        // Parameter zum Request hinzufügen
        $requestWithParams = $this->addParametersToRequest($request, $parameters);

        // Middleware-Chain aufbauen
        $middlewares = $route->middlewares;
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

        return Response::notFound(self::DEFAULT_404_MESSAGE);
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

        return Response::methodNotAllowed(self::DEFAULT_405_MESSAGE)
            ->withHeader('Allow', implode(', ', $allowedMethods));
    }

    /**
     * Debug-Information über geladene Routes
     */
    public function getRoutes(): array
    {
        $this->loadRoutes();

        return array_map(function (RouteEntry $route) {
            return [
                'pattern' => $route->pattern,
                'methods' => array_map(fn(HttpMethod $m) => $m->value, $route->methods),
                'action' => $route->action,
                'middlewares' => $route->middlewares,
                'name' => $route->name,
                'parameters' => $route->parameters,
            ];
        }, $this->routes);
    }

    /**
     * Löscht Route-Cache
     */
    public function clearCache(): bool
    {
        $this->routesLoaded = false;
        $this->routes = [];
        $this->namedRoutes = [];

        return $this->cache->clearCache();
    }
}