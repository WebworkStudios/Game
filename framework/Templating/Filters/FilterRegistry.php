<?php
declare(strict_types=1);

namespace Framework\Templating\Filters;

use RuntimeException;

/**
 * FilterRegistry - KORRIGIERT für statische Methoden-Arrays
 *
 * FIXES:
 * - Unterstützt Arrays als callable: [ClassName::class, 'methodName']
 * - Validiert callable korrekt für statische Methoden
 * - Bessere Error-Messages für debugging
 */
class FilterRegistry
{
    /** @var array<string, callable|array> Bereits geladene Filter */
    private array $filters = [];

    /** @var array<string, callable> Lazy Filter Factories */
    private array $lazyFilters = [];

    /**
     * KORRIGIERT: Registriert einen Filter (akzeptiert callable UND Arrays)
     */
    public function register(string $name, callable|array $filter): void
    {
        // Validiere dass das übergebene Filter wirklich callable ist
        if (!is_callable($filter)) {
            throw new RuntimeException(
                "Filter '{$name}' is not callable. Received: " . $this->getCallableDebugInfo($filter)
            );
        }

        $this->filters[$name] = $filter;
    }

    /**
     * Registriert einen Lazy Filter (wird erst bei Bedarf geladen)
     */
    public function registerLazy(string $name, callable $factory): void
    {
        $this->lazyFilters[$name] = $factory;
    }

    /**
     * Prüft ob ein Filter existiert (geladen oder lazy)
     */
    public function has(string $name): bool
    {
        return isset($this->filters[$name]) || isset($this->lazyFilters[$name]);
    }

    /**
     * KORRIGIERT: Gibt einen Filter zurück, lädt ihn falls nötig
     */
    public function get(string $name): callable
    {
        // Bereits geladen?
        if (isset($this->filters[$name])) {
            $filter = $this->filters[$name];

            // Double-check dass Filter wirklich callable ist
            if (!is_callable($filter)) {
                throw new RuntimeException(
                    "Filter '{$name}' is registered but not callable: " . $this->getCallableDebugInfo($filter)
                );
            }

            return $filter;
        }

        // Lazy loading
        if (isset($this->lazyFilters[$name])) {
            $factory = $this->lazyFilters[$name];
            $filter = $factory();

            // Validiere dass Factory ein callable zurückgibt
            if (!is_callable($filter)) {
                throw new RuntimeException(
                    "Lazy filter factory for '{$name}' returned non-callable: " . $this->getCallableDebugInfo($filter)
                );
            }

            $this->filters[$name] = $filter;
            return $this->filters[$name];
        }

        throw new RuntimeException("Filter '{$name}' not found. Available filters: " . implode(', ', $this->getFilterNames()));
    }

    /**
     * Entfernt einen Filter
     */
    public function remove(string $name): void
    {
        unset($this->filters[$name], $this->lazyFilters[$name]);
    }

    /**
     * Leert die Registry
     */
    public function clear(): void
    {
        $this->filters = [];
        $this->lazyFilters = [];
    }

    /**
     * Gibt die Anzahl der verfügbaren Filter zurück
     */
    public function count(): int
    {
        return count($this->getFilterNames());
    }

    /**
     * Gibt alle verfügbaren Filter-Namen zurück
     */
    public function getFilterNames(): array
    {
        return array_unique(array_merge(
            array_keys($this->filters),
            array_keys($this->lazyFilters)
        ));
    }

    /**
     * HINZUGEFÜGT: Debug-Informationen für callable-Probleme
     */
    private function getCallableDebugInfo(mixed $value): string
    {
        if (is_array($value)) {
            if (count($value) === 2) {
                [$class, $method] = $value;

                if (is_string($class) && is_string($method)) {
                    $classExists = class_exists($class);
                    $methodExists = $classExists && method_exists($class, $method);

                    return sprintf(
                        "Array [%s, %s] - Class exists: %s, Method exists: %s",
                        $class,
                        $method,
                        $classExists ? 'YES' : 'NO',
                        $methodExists ? 'YES' : 'NO'
                    );
                }
            }

            return "Array with " . count($value) . " elements: " . var_export($value, true);
        }

        if (is_object($value)) {
            return "Object of class " . get_class($value);
        }

        return gettype($value) . ": " . var_export($value, true);
    }

    /**
     * HINZUGEFÜGT: Validiert callable mit detailliertem Feedback
     */
    public function validateCallable(mixed $filter, string $name = 'unknown'): bool
    {
        if (is_callable($filter)) {
            return true;
        }

        // Detaillierte Analyse für besseres Debugging
        if (is_array($filter) && count($filter) === 2) {
            [$class, $method] = $filter;

            if (!is_string($class)) {
                throw new RuntimeException("Filter '{$name}': First array element must be class name string, got " . gettype($class));
            }

            if (!is_string($method)) {
                throw new RuntimeException("Filter '{$name}': Second array element must be method name string, got " . gettype($method));
            }

            if (!class_exists($class)) {
                throw new RuntimeException("Filter '{$name}': Class '{$class}' does not exist");
            }

            if (!method_exists($class, $method)) {
                throw new RuntimeException("Filter '{$name}': Method '{$method}' does not exist in class '{$class}'");
            }

            // Prüfe ob Methode public und static ist
            $reflection = new \ReflectionMethod($class, $method);
            if (!$reflection->isPublic()) {
                throw new RuntimeException("Filter '{$name}': Method '{$class}::{$method}' must be public");
            }

            if (!$reflection->isStatic()) {
                throw new RuntimeException("Filter '{$name}': Method '{$class}::{$method}' must be static");
            }
        }

        return false;
    }

    /**
     * HINZUGEFÜGT: Sichere Filter-Registrierung mit Validierung
     */
    public function registerSafe(string $name, callable|array $filter): bool
    {
        try {
            $this->validateCallable($filter, $name);
            $this->register($name, $filter);
            return true;
        } catch (RuntimeException $e) {
            // Log error but don't crash
            error_log("Failed to register filter '{$name}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * HINZUGEFÜGT: Bulk-Registration mit Error-Handling
     */
    public function registerMultiple(array $filters): array
    {
        $errors = [];

        foreach ($filters as $name => $filter) {
            if (!$this->registerSafe($name, $filter)) {
                $errors[] = $name;
            }
        }

        return $errors;
    }

    /**
     * HINZUGEFÜGT: Debug-Informationen für alle Filter
     */
    public function getDebugInfo(): array
    {
        $info = [
            'total_filters' => $this->count(),
            'loaded_filters' => count($this->filters),
            'lazy_filters' => count($this->lazyFilters),
            'filter_details' => []
        ];

        // Detaillierte Filter-Info
        foreach ($this->filters as $name => $filter) {
            $info['filter_details'][$name] = [
                'type' => 'loaded',
                'callable' => is_callable($filter),
                'info' => $this->getCallableDebugInfo($filter)
            ];
        }

        foreach ($this->lazyFilters as $name => $factory) {
            $info['filter_details'][$name] = [
                'type' => 'lazy',
                'callable' => is_callable($factory),
                'info' => 'Lazy factory'
            ];
        }

        return $info;
    }
}