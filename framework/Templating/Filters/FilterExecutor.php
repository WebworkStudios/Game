<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

use RuntimeException;

/**
 * FilterExecutor - Clean Implementation für KickersCup Manager
 *
 * Verantwortlichkeiten:
 * - Filter-Ausführung mit Argumenten
 * - Fehlerbehandlung bei Filter-Ausführung
 * - Performance-optimierte Ausführung
 */
final readonly class FilterExecutor
{
    public function __construct(
        private FilterRegistry $registry
    ) {}

    /**
     * Filter ausführen
     */
    public function execute(string $filterName, mixed $value, array $arguments = []): mixed
    {
        $filter = $this->registry->get($filterName);

        try {
            return $this->callFilter($filter, $value, $arguments);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Filter '{$filterName}' execution failed: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Filter mit Argumenten aufrufen
     */
    private function callFilter(callable $filter, mixed $value, array $arguments): mixed
    {
        // Wert ist immer das erste Argument
        $allArguments = [$value, ...$arguments];

        return $filter(...$allArguments);
    }

    /**
     * Mehrere Filter hintereinander ausführen (Pipeline)
     */
    public function executePipeline(mixed $value, array $filterPipeline): mixed
    {
        $result = $value;

        foreach ($filterPipeline as $filterData) {
            if (is_string($filterData)) {
                // Einfacher Filter ohne Argumente
                $result = $this->execute($filterData, $result);
            } elseif (is_array($filterData) && isset($filterData['name'])) {
                // Filter mit Argumenten
                $filterName = $filterData['name'];
                $arguments = $filterData['arguments'] ?? [];
                $result = $this->execute($filterName, $result, $arguments);
            } else {
                throw new RuntimeException('Invalid filter pipeline data');
            }
        }

        return $result;
    }

    /**
     * Prüft ob Filter ausführbar ist
     */
    public function canExecute(string $filterName): bool
    {
        try {
            $filter = $this->registry->get($filterName);
            return is_callable($filter);
        } catch (RuntimeException) {
            return false;
        }
    }
}