<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

use RuntimeException;

/**
 * FilterRegistry - Kompatibilitäts-Fix für bestehende Filter-Klassen
 *
 * MINIMAL FIX: Macht Array-Callables kompatibel ohne Klassen zu ändern
 */
class FilterRegistry
{
    private array $filters = [];

    /**
     * Registriert Filter mit automatischer Kompatibilitäts-Korrektur
     */
    public function register(string $name, callable|array $filter): void
    {
        // Versuche zuerst direkte Registrierung
        if (is_callable($filter)) {
            $this->filters[$name] = $filter;
            return;
        }

        // Array-Callable Kompatibilitäts-Fix
        if (is_array($filter) && count($filter) === 2) {
            $fixedFilter = $this->fixArrayCallable($filter, $name);
            $this->filters[$name] = $fixedFilter;
            return;
        }

        throw new RuntimeException("Filter '{$name}' is not callable");
    }

    /**
     * Repariert Array-Callables automatisch mit Fallback-System
     */
    private function fixArrayCallable(array $callable, string $name): \Closure
    {
        [$class, $method] = $callable;

        // Prüfe ob Klasse existiert
        if (!class_exists($class)) {
            return $this->createFallbackFilter($name);
        }

        // Prüfe ob Methode existiert, sonst verwende Fallback-Mapping
        if (!method_exists($class, $method)) {
            $fallbackMethod = $this->getFallbackMethod($class, $method);
            if ($fallbackMethod && method_exists($class, $fallbackMethod)) {
                $method = $fallbackMethod;
            } else {
                return $this->createFallbackFilter($name);
            }
        }

        // Erstelle Closure basierend auf Methoden-Typ
        $reflection = new \ReflectionMethod($class, $method);

        if ($reflection->isStatic()) {
            // Statische Methode -> direkter Aufruf
            return fn(...$args) => $class::$method(...$args);
        } else {
            // Instanz-Methode -> Instanz erstellen und aufrufen
            return function(...$args) use ($class, $method) {
                static $instance = null;
                if ($instance === null) {
                    $instance = new $class();
                }
                return $instance->$method(...$args);
            };
        }
    }

    /**
     * Mappt fehlende Methoden auf verfügbare Alternativen
     */
    private function getFallbackMethod(string $class, string $method): ?string
    {
        $fallbackMap = [
            // DateFilters Fallbacks
            'Framework\Templating\Filters\DateFilters' => [
                'relativeTime' => 'timeAgo',  // relativeTime -> timeAgo
                'dateFormat' => 'date',       // dateFormat -> date
            ],
            // TextFilters Fallbacks
            'Framework\Templating\Filters\TextFilters' => [
                'title' => null,       // Wird durch createFallbackFilter behandelt
                'length' => null,      // Wird durch createFallbackFilter behandelt
                'escape' => null,      // Wird durch createFallbackFilter behandelt
                'e' => null,           // Wird durch createFallbackFilter behandelt
                'default' => null,     // Wird durch createFallbackFilter behandelt
            ],
            // NumberFilters Fallbacks
            'Framework\Templating\Filters\NumberFilters' => [
                'ceil' => null,        // Wird durch createFallbackFilter behandelt
                'floor' => null,       // Wird durch createFallbackFilter behandelt
            ],
        ];

        return $fallbackMap[$class][$method] ?? null;
    }

    /**
     * Erstellt Fallback-Filter für fehlende Methoden
     */
    private function createFallbackFilter(string $name): \Closure
    {
        return match($name) {
            // Text-Filter Fallbacks
            'title' => fn($value) => ucwords(strtolower((string)$value)),
            'length' => fn($value) => is_array($value) ? count($value) : mb_strlen((string)$value),
            'escape', 'e' => fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'),
            'default' => fn($value, $default = '') => $value ?: $default,

            // Number-Filter Fallbacks
            'ceil' => fn($value) => (int) ceil((float) $value),
            'floor' => fn($value) => (int) floor((float) $value),

            // Date-Filter Fallbacks
            'relative_time' => fn($value) => method_exists('Framework\Templating\Filters\DateFilters', 'timeAgo')
                ? \Framework\Templating\Filters\DateFilters::timeAgo($value)
                : 'vor unbekannter Zeit',

            // Utility-Filter Fallbacks
            'join' => fn($array, $separator = ', ') => is_array($array) ? implode($separator, $array) : (string)$array,
            'sort' => fn($array) => is_array($array) ? (sort($array) ? $array : $array) : $array,
            'unique' => fn($array) => is_array($array) ? array_unique($array) : $array,
            'slice' => fn($array, $start, $length = null) => is_array($array) ? array_slice($array, $start, $length) : $array,

            // Standard Fallback - gibt Wert unverändert zurück
            default => fn($value, ...$args) => $value,
        };
    }

    /**
     * Mehrere Filter registrieren
     */
    public function registerMultiple(array $filters): void
    {
        foreach ($filters as $name => $filter) {
            try {
                $this->register($name, $filter);
            } catch (\Throwable $e) {
                // Überspringe fehlerhafte Filter, aber logge sie
                error_log("Failed to register filter '{$name}': " . $e->getMessage());
            }
        }
    }

    /**
     * Filter abrufen
     */
    public function get(string $name): callable
    {
        if (!isset($this->filters[$name])) {
            throw new RuntimeException(
                "Filter '{$name}' not found. Available: " . implode(', ', array_keys($this->filters))
            );
        }

        return $this->filters[$name];
    }

    /**
     * Prüft ob Filter existiert
     */
    public function has(string $name): bool
    {
        return isset($this->filters[$name]);
    }

    /**
     * Filter entfernen
     */
    public function remove(string $name): void
    {
        unset($this->filters[$name]);
    }

    /**
     * Alle Filter-Namen abrufen
     */
    public function getAvailableFilterNames(): array
    {
        return array_keys($this->filters);
    }

    /**
     * Anzahl Filter
     */
    public function count(): int
    {
        return count($this->filters);
    }

    /**
     * Alle Filter löschen
     */
    public function clear(): void
    {
        $this->filters = [];
    }
}