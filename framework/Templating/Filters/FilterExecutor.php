<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

use RuntimeException;

/**
 * FilterExecutor - Ausführungslogik für Filter
 *
 * Verantwortlichkeiten:
 * - Filter-Ausführung mit Parametern
 * - Fehlerbehandlung bei Filter-Ausführung
 * - Parameter-Validierung
 */
class FilterExecutor
{
    public function __construct(
        private readonly FilterRegistry $registry
    ) {}

    /**
     * Führt einen Filter aus
     */
    public function execute(string $filterName, mixed $value, array $parameters = []): mixed
    {
        try {
            $filter = $this->registry->get($filterName);

            // Filter mit Parametern ausführen
            return $filter($value, ...$parameters);

        } catch (RuntimeException $e) {
            // Registry-Fehler weiterleiten
            throw $e;
        } catch (\Throwable $e) {
            // Filter-Execution-Fehler
            throw new RuntimeException(
                "Filter '{$filterName}' execution failed: " . $e->getMessage(),
                previous: $e
            );
        }
    }

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