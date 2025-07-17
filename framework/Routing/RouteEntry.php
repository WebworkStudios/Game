<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Http\HttpMethod;

/**
 * Route Entry - Container für Route-Informationen
 *
 * ERWEITERT: Mit generateUrl Methode für URL-Generierung
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

    /**
     * NEU: Generiert URL aus Route-Pattern und Parametern
     */
    public function generateUrl(array $parameters = []): string
    {
        // Route-Pattern zu URL-Template konvertieren
        $urlTemplate = $this->convertPatternToUrlTemplate();

        // Parameter einsetzen
        $url = $urlTemplate;
        foreach ($parameters as $key => $value) {
            $url = str_replace('{' . $key . '}', (string) $value, $url);
        }

        // Prüfe ob alle Parameter ersetzt wurden
        if (preg_match('/\{[^}]+}/', $url)) {
            $missingParams = [];
            preg_match_all('/\{([^}]+)}/', $url, $matches);
            $missingParams = $matches[1];

            throw new \InvalidArgumentException(
                "Missing parameters for route '{$this->name}': " . implode(', ', $missingParams)
            );
        }

        return $url;
    }

    /**
     * Konvertiert Regex-Pattern zu URL-Template
     */
    private function convertPatternToUrlTemplate(): string
    {
        // Entferne Regex-Wrapper (#^...$#)
        $pattern = $this->pattern;
        $pattern = preg_replace('/^#\^/', '', $pattern);
        $pattern = preg_replace('/\$#$/', '', $pattern);

        // Konvertiere Named Captures zurück zu Parameter-Platzhaltern
        // (?P<id>[^/]+) -> {id}
        $pattern = preg_replace('/\(\?P<([^>]+)>[^)]+\)/', '{$1}', $pattern);

        return $pattern;
    }

    /**
     * Erstellt Route-Informationen für Debug-Ausgabe
     */
    public function toArray(): array
    {
        return [
            'pattern' => $this->pattern,
            'methods' => array_map(fn(HttpMethod $method) => $method->value, $this->methods),
            'action' => $this->action,
            'middlewares' => $this->middlewares,
            'name' => $this->name,
            'parameters' => $this->parameters,
        ];
    }

    /**
     * Prüft ob Route Parameter benötigt
     */
    public function hasParameters(): bool
    {
        return !empty($this->parameters);
    }

    /**
     * Prüft ob Route einen spezifischen Parameter hat
     */
    public function hasParameter(string $name): bool
    {
        return in_array($name, $this->parameters, true);
    }

    /**
     * Holt alle Parameter-Namen
     */
    public function getParameterNames(): array
    {
        return $this->parameters;
    }

    /**
     * Prüft ob Route GET-Methode unterstützt
     */
    public function supportsGet(): bool
    {
        return $this->supportsMethod(HttpMethod::GET);
    }

    /**
     * Prüft ob Route POST-Methode unterstützt
     */
    public function supportsPost(): bool
    {
        return $this->supportsMethod(HttpMethod::POST);
    }

    /**
     * Prüft ob Route PUT-Methode unterstützt
     */
    public function supportsPut(): bool
    {
        return $this->supportsMethod(HttpMethod::PUT);
    }

    /**
     * Prüft ob Route DELETE-Methode unterstützt
     */
    public function supportsDelete(): bool
    {
        return $this->supportsMethod(HttpMethod::DELETE);
    }

    /**
     * Holt alle unterstützten HTTP-Methoden als Strings
     */
    public function getMethodStrings(): array
    {
        return array_map(fn(HttpMethod $method) => $method->value, $this->methods);
    }

    /**
     * Prüft ob Route Middleware hat
     */
    public function hasMiddleware(): bool
    {
        return !empty($this->middlewares);
    }

    /**
     * Prüft ob Route spezifische Middleware hat
     */
    public function hasMiddlewareClass(string $middlewareClass): bool
    {
        return in_array($middlewareClass, $this->middlewares, true);
    }

    /**
     * Holt Action-Klassenname ohne Namespace
     */
    public function getActionClassName(): string
    {
        $parts = explode('\\', $this->action);
        return end($parts);
    }

    /**
     * Holt Action-Namespace
     */
    public function getActionNamespace(): string
    {
        $parts = explode('\\', $this->action);
        array_pop($parts); // Entferne Klassenname
        return implode('\\', $parts);
    }

    /**
     * Prüft ob Route ein benannter Route ist
     */
    public function isNamed(): bool
    {
        return $this->name !== null;
    }

    /**
     * String-Darstellung der Route
     */
    public function __toString(): string
    {
        $methods = implode('|', $this->getMethodStrings());
        $name = $this->name ? " ({$this->name})" : '';
        return "[{$methods}] {$this->pattern} -> {$this->action}{$name}";
    }

    /**
     * Debug-Ausgabe der Route
     */
    public function dump(): void
    {
        echo "\n=== ROUTE ENTRY DEBUG ===\n";
        echo "Pattern: {$this->pattern}\n";
        echo "Methods: " . implode(', ', $this->getMethodStrings()) . "\n";
        echo "Action: {$this->action}\n";
        echo "Name: " . ($this->name ?? 'unnamed') . "\n";
        echo "Parameters: " . implode(', ', $this->parameters) . "\n";
        echo "Middlewares: " . implode(', ', $this->middlewares) . "\n";
        echo "========================\n\n";
    }
}