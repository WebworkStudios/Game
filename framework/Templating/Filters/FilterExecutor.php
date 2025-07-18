<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

use RuntimeException;

/**
 * FilterExecutor - Verbesserte Ausführungslogik für Filter
 *
 * KORRIGIERT: Besseres Debugging und Null-Handling
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
     *
     * KORRIGIERT: Besseres Debugging und Fehlerbehandlung
     */
    public function execute(string $filterName, mixed $value, array $parameters = []): mixed
    {
        try {
            // DEBUG: Log Filter-Ausführung
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("FilterExecutor: Executing filter '{$filterName}' with value: " .
                    var_export($value, true) . " (type: " . gettype($value) . ")");
                error_log("FilterExecutor: Parameters: " . var_export($parameters, true));
            }

            $filter = $this->registry->get($filterName);

            // DEBUG: Validate that we have a callable
            if (!is_callable($filter)) {
                throw new RuntimeException("Filter '{$filterName}' is not callable");
            }

            // KORRIGIERT: Additional validation for JavaScript filters
            if (str_starts_with($filterName, 'js_') && $value === null) {
                if ($_ENV['APP_DEBUG'] ?? false) {
                    error_log("FilterExecutor: WARNING - JavaScript filter '{$filterName}' received null value");
                }

                // For JavaScript filters, null values should return empty string
                return '';
            }

            // Filter mit Parametern ausführen
            $result = $filter($value, ...$parameters);

            // DEBUG: Log result
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("FilterExecutor: Filter '{$filterName}' returned: " .
                    var_export($result, true) . " (type: " . gettype($result) . ")");
            }

            return $result;

        } catch (RuntimeException $e) {
            // Registry-Fehler weiterleiten
            throw $e;
        } catch (\Throwable $e) {
            // KORRIGIERT: Detailliertere Fehlermeldung
            $errorContext = [
                'filter' => $filterName,
                'value_type' => gettype($value),
                'value' => is_scalar($value) ? $value : '[' . gettype($value) . ']',
                'parameters' => $parameters,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ];

            error_log("FilterExecutor ERROR: " . json_encode($errorContext, JSON_PRETTY_PRINT));

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