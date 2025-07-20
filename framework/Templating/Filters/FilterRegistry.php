<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

use RuntimeException;
use Generator;

/**
 * FilterRegistry - OPTIMIZED with PHP 8.4 iterator_to_array() Features
 */
class FilterRegistry
{
    /** @var array<string, callable|array> Bereits geladene Filter */
    private array $filters = [];

    /** @var array<string, callable> Lazy Filter Factories */
    private array $lazyFilters = [];

    /** @var array<string, array> Filter-Metadaten für Debugging */
    private array $filterMetadata = [];

    /** @var array<string, int> Filter-Zugriffszähler für Performance-Monitoring */
    private array $accessCounts = [];

    /**
     * OPTIMIZED: Registriert einen Filter mit Metadaten-Tracking
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
        $this->filterMetadata[$name] = $this->extractFilterMetadata($filter);
        $this->accessCounts[$name] = 0;
    }

    /**
     * OPTIMIZED: Bulk-Registrierung mit chunked processing
     */
    public function registerMultiple(array $filters, int $chunkSize = 50): array
    {
        $errors = [];
        $chunks = array_chunk($filters, $chunkSize, true);

        foreach ($chunks as $chunk) {
            $chunkErrors = $this->processFilterChunk($chunk);
            $errors = array_merge($errors, $chunkErrors);

            // Optional: Garbage collection zwischen Chunks
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        return $errors;
    }

    /**
     * OPTIMIZED: Verarbeitet Filter-Chunk mit lazy validation
     */
    private function processFilterChunk(array $chunk): array
    {
        $errors = [];

        $validationGenerator = function() use ($chunk, &$errors) {
            foreach ($chunk as $name => $filter) {
                try {
                    $this->validateCallable($filter, $name);
                    yield $name => $filter;
                } catch (RuntimeException $e) {
                    $errors[] = $name;
                    error_log("Failed to register filter '{$name}': " . $e->getMessage());
                }
            }
        };

        // Konvertiere validierte Filter zu Array
        $validatedFilters = iterator_to_array($validationGenerator(), preserve_keys: true);

        // Registriere alle validierten Filter
        foreach ($validatedFilters as $name => $filter) {
            $this->register($name, $filter);
        }

        return $errors;
    }

    /**
     * OPTIMIZED: Lazy Filter-Registrierung mit Dependency-Tracking
     */
    public function registerLazy(string $name, callable $factory, array $dependencies = []): void
    {
        $this->lazyFilters[$name] = $factory;
        $this->filterMetadata[$name] = [
            'type' => 'lazy',
            'dependencies' => $dependencies,
            'factory_info' => $this->getCallableDebugInfo($factory),
        ];
        $this->accessCounts[$name] = 0;
    }

    /**
     * OPTIMIZED: Filter abrufen mit lazy loading und access tracking
     */
    public function get(string $name): callable
    {
        // Track access für Performance-Monitoring
        $this->accessCounts[$name] = ($this->accessCounts[$name] ?? 0) + 1;

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

        // Lazy loading mit Dependency-Resolution
        if (isset($this->lazyFilters[$name])) {
            $filter = $this->loadLazyFilter($name);

            // Cache den geladenen Filter
            $this->filters[$name] = $filter;

            // Update Metadaten
            $this->filterMetadata[$name]['loaded_at'] = microtime(true);
            $this->filterMetadata[$name]['type'] = 'loaded_from_lazy';

            return $filter;
        }

        throw new RuntimeException("Filter '{$name}' not found. Available filters: " .
            implode(', ', $this->getAvailableFilterNames()));
    }

    /**
     * OPTIMIZED: Lazy Filter laden mit Dependency-Resolution
     */
    private function loadLazyFilter(string $name): callable
    {
        $factory = $this->lazyFilters[$name];
        $dependencies = $this->filterMetadata[$name]['dependencies'] ?? [];

        // Lade Dependencies falls nötig
        foreach ($dependencies as $dependency) {
            if (!$this->has($dependency)) {
                throw new RuntimeException(
                    "Filter '{$name}' depends on '{$dependency}' which is not available"
                );
            }

            // Lade Dependency (triggert recursive lazy loading)
            $this->get($dependency);
        }

        $filter = $factory();

        // Validiere dass Factory ein callable zurückgibt
        if (!is_callable($filter)) {
            throw new RuntimeException(
                "Lazy filter factory for '{$name}' returned non-callable: " . $this->getCallableDebugInfo($filter)
            );
        }

        return $filter;
    }

    /**
     * OPTIMIZED: Filter-Details mit lazy evaluation
     */
    private function getFilterDetailsLazy(): array
    {
        $loadedGenerator = $this->getLoadedFiltersDebugGenerator();
        $lazyGenerator = $this->getLazyFiltersDebugGenerator();

        return [
            'loaded' => iterator_to_array($loadedGenerator, preserve_keys: true),
            'lazy' => iterator_to_array($lazyGenerator, preserve_keys: true),
        ];
    }

    /**
     * OPTIMIZED: Loaded Filters Debug Info als Generator
     */
    private function getLoadedFiltersDebugGenerator(): Generator
    {
        foreach ($this->filters as $name => $filter) {
            yield $name => [
                'type' => 'loaded',
                'callable' => is_callable($filter),
                'access_count' => $this->accessCounts[$name] ?? 0,
                'metadata' => $this->filterMetadata[$name] ?? [],
                'info' => $this->getCallableDebugInfo($filter),
            ];
        }
    }

    /**
     * OPTIMIZED: Lazy Filters Debug Info als Generator
     */
    private function getLazyFiltersDebugGenerator(): Generator
    {
        foreach ($this->lazyFilters as $name => $factory) {
            yield $name => [
                'type' => 'lazy',
                'callable' => is_callable($factory),
                'access_count' => $this->accessCounts[$name] ?? 0,
                'metadata' => $this->filterMetadata[$name] ?? [],
                'dependencies' => $this->filterMetadata[$name]['dependencies'] ?? [],
                'factory_info' => $this->getCallableDebugInfo($factory),
            ];
        }
    }

    /**
     * OPTIMIZED: Comprehensive filter validation
     */
    public function validateAll(): array
    {
        $results = [
            'valid' => [],
            'invalid' => [],
            'lazy_invalid' => [],
        ];

        // Validate loaded filters
        foreach ($this->filters as $name => $filter) {
            if (is_callable($filter)) {
                $results['valid'][] = $name;
            } else {
                $results['invalid'][] = $name;
            }
        }

        // Validate lazy filters (test factory callability)
        foreach ($this->lazyFilters as $name => $factory) {
            if (!is_callable($factory)) {
                $results['lazy_invalid'][] = $name;
            }
        }

        return $results;
    }

    /**
     * OPTIMIZED: Bulk filter removal mit lazy processing
     */
    public function removeMultiple(array $filterNames): array
    {
        $removed = [];
        $notFound = [];

        foreach ($filterNames as $name) {
            if ($this->has($name)) {
                $this->remove($name);
                $removed[] = $name;
            } else {
                $notFound[] = $name;
            }
        }

        return [
            'removed' => $removed,
            'not_found' => $notFound,
        ];
    }

    /**
     * OPTIMIZED: Filter-Cleanup (entfernt ungenutzte Filter)
     */
    public function cleanup(int $minAccessCount = 1): array
    {
        $removed = [];

        $unusedFilters = array_filter(
            $this->accessCounts,
            fn($count) => $count < $minAccessCount
        );

        foreach (array_keys($unusedFilters) as $name) {
            $this->remove($name);
            $removed[] = $name;
        }

        return $removed;
    }

    /**
     * Prüft ob ein Filter existiert (geladen oder lazy)
     */
    public function has(string $name): bool
    {
        return isset($this->filters[$name]) || isset($this->lazyFilters[$name]);
    }

    /**
     * OPTIMIZED: Enhanced remove mit cleanup
     */
    public function remove(string $name): void
    {
        unset($this->filters[$name]);
        unset($this->lazyFilters[$name]);
        unset($this->filterMetadata[$name]);
        unset($this->accessCounts[$name]);
    }

    /**
     * OPTIMIZED: Count mit lazy filters
     */
    public function count(): int
    {
        return count($this->filters) + count($this->lazyFilters);
    }

    /**
     * OPTIMIZED: Available filter names mit lazy evaluation
     */
    public function getAvailableFilterNames(): array
    {
        $generator = function() {
            foreach (array_keys($this->filters) as $name) {
                yield $name;
            }
            foreach (array_keys($this->lazyFilters) as $name) {
                yield $name;
            }
        };

        return iterator_to_array($generator(), preserve_keys: false);
    }

    /**
     * OPTIMIZED: Extract filter metadata for debugging
     */
    private function extractFilterMetadata(callable|array $filter): array
    {
        $metadata = [
            'registered_at' => microtime(true),
            'type' => 'standard',
        ];

        if (is_array($filter) && count($filter) === 2) {
            [$class, $method] = $filter;
            $metadata['class'] = $class;
            $metadata['method'] = $method;

            if (class_exists($class)) {
                $reflection = new \ReflectionMethod($class, $method);
                $metadata['is_static'] = $reflection->isStatic();
                $metadata['is_public'] = $reflection->isPublic();
                $metadata['parameters'] = array_map(
                    fn(\ReflectionParameter $param) => $param->getName(),
                    $reflection->getParameters()
                );
            }
        } elseif (is_callable($filter)) {
            $metadata['callable_type'] = 'closure';
        }

        return $metadata;
    }

    /**
     * OPTIMIZED: Enhanced callable validation
     */
    private function validateCallable(callable|array $filter, string $name): void
    {
        if (!is_callable($filter)) {
            throw new RuntimeException(
                "Filter '{$name}' is not callable. Received: " . $this->getCallableDebugInfo($filter)
            );
        }

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
    }

    /**
     * OPTIMIZED: Enhanced debug info for callables
     */
    private function getCallableDebugInfo(mixed $callable): string
    {
        if (is_string($callable)) {
            return "string function: '{$callable}'";
        }

        if (is_array($callable)) {
            if (count($callable) === 2) {
                [$class, $method] = $callable;
                return "array method: [{$class}, {$method}]";
            }
            return "invalid array: " . json_encode($callable);
        }

        if ($callable instanceof \Closure) {
            $reflection = new \ReflectionFunction($callable);
            $file = $reflection->getFileName();
            $line = $reflection->getStartLine();
            return "closure defined in {$file}:{$line}";
        }

        if (is_object($callable)) {
            return "object method: " . get_class($callable) . "->__invoke()";
        }

        return "unknown type: " . gettype($callable);
    }
}