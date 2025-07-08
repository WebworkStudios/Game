<?php


declare(strict_types=1);

namespace Framework\Routing;

use Framework\Http\HttpMethod;

/**
 * Route Entry - Container für Route-Informationen
 */
readonly class RouteEntry
{
    /**
     * @param string $pattern Regex-Pattern für URL-Matching
     * @param array<HttpMethod> $methods Erlaubte HTTP-Methoden
     * @param class-string $action Action-Klasse
     * @param array<class-string> $middlewares Middleware-Klassen
     * @param string|null $name Route-Name
     * @param array<string> $parameters Parameter-Namen
     */
    public function __construct(
        public string  $pattern,
        public array   $methods,
        public string  $action,
        public array   $middlewares,
        public ?string $name,
        public array   $parameters,
    )
    {
    }

    /**
     * Prüft ob HTTP-Methode unterstützt wird
     */
    public function supportsMethod(HttpMethod $method): bool
    {
        return in_array($method, $this->methods, true);
    }

    /**
     * Matched Request-Pfad gegen Route-Pattern
     */
    public function matches(string $path): array|false
    {
        if (preg_match($this->pattern, $path, $matches)) {
            // Nur Named Captures zurückgeben
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return false;
    }
}