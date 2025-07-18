<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

use RuntimeException;

/**
 * FilterExecutor - Verbesserte Ausführungslogik für Filter
 *
 * BEREINIGT: Debug-Logging komplett entfernt
 */
class FilterExecutor
{
    public function __construct(
        private readonly FilterRegistry $registry
    ) {}

    /**
     * Führt mehrere Filter nacheinander aus (Pipeline)
     */
    public function executePipeline(mixed $value, array $filterPipeline): mixed
    {
        foreach ($filterPipeline as $filterConfig) {
            if (is_string($filterConfig)) {
                // Einfacher Filter ohne Parameter
                $value = $this->execute($filterConfig, $value);
            } elseif (is_array($filterConfig)) {
                // Filter mit Parametern
                $filterName = $filterConfig['name'] ?? $filterConfig[0] ?? null;
                $parameters = $filterConfig['parameters'] ?? array_slice($filterConfig, 1);

                if ($filterName === null) {
                    throw new RuntimeException('Filter name missing in pipeline configuration');
                }

                $value = $this->execute($filterName, $value, $parameters);
            } else {
                throw new RuntimeException('Invalid filter configuration in pipeline');
            }
        }

        return $value;
    }

    /**
     * Führt einen Filter aus
     */
    public function execute(string $filterName, mixed $value, array $parameters = []): mixed
    {
        try {
            $filter = $this->registry->get($filterName);

            // Validate that we have a callable
            if (!is_callable($filter)) {
                throw new RuntimeException("Filter '{$filterName}' is not callable");
            }

            // Additional validation for JavaScript filters
            if (str_starts_with($filterName, 'js_') && $value === null) {
                // For JavaScript filters, null values should return empty string
                return '';
            }

            // Filter mit Parametern ausführen
            return $filter($value, ...$parameters);

        } catch (RuntimeException $e) {
            // Registry-Fehler weiterleiten
            throw $e;
        } catch (\Throwable $e) {
            // Filter-Execution-Fehler
            throw new RuntimeException(
                "Filter '{$filterName}' execution failed: " . $e->getMessage() .
                " (received " . gettype($value) . " value)",
                previous: $e
            );
        }
    }

    /**
     * Prüft ob ein Filter existiert
     */
    public function hasFilter(string $filterName): bool
    {
        return $this->registry->has($filterName);
    }

    /**
     * Gibt alle verfügbaren Filter zurück
     */
    public function getAvailableFilters(): array
    {
        return $this->registry->getFilterNames();
    }
}