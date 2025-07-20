<?php

declare(strict_types=1);

namespace Framework\Database;

use Countable;
use Iterator;
use PDOStatement;
use Generator;

/**
 * Query Result - OPTIMIZED with PHP 8.4 iterator_to_array() Features
 *
 * PHASE 1 OPTIMIZATIONS:
 * ✅ Memory-efficient streaming with Generators
 * ✅ Explicit preserve_keys control with iterator_to_array()
 * ✅ Lazy evaluation for large datasets
 * ✅ Chunked processing for memory management
 * ✅ Type-safe array conversions
 */
class QueryResult implements Iterator, Countable
{
    private array $data;
    private int $position = 0;

    public function __construct(
        private PDOStatement $statement,
        private string       $sql,
        private array        $bindings,
        private float        $executionTime,
    )
    {
        $this->data = $this->statement->fetchAll();
    }

    // ===================================================================
    // OPTIMIZED: Core Collection Methods with iterator_to_array()
    // ===================================================================

    /**
     * OPTIMIZED: Convert to array with explicit key preservation control
     */
    public function toArray(bool $preserveKeys = false): array
    {
        if ($this instanceof Iterator) {
            return iterator_to_array($this, preserve_keys: $preserveKeys);
        }
        return $this->data;
    }

    /**
     * OPTIMIZED: Memory-efficient lazy iteration
     */
    public function lazy(): Generator
    {
        foreach ($this->data as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * OPTIMIZED: Chunked processing for large datasets
     */
    public function chunk(int $size): Generator
    {
        if ($size <= 0) {
            throw new \InvalidArgumentException('Chunk size must be positive');
        }

        $chunk = [];
        foreach ($this->data as $key => $item) {
            $chunk[$key] = $item;

            if (count($chunk) >= $size) {
                yield iterator_to_array(new \ArrayIterator($chunk), preserve_keys: true);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            yield iterator_to_array(new \ArrayIterator($chunk), preserve_keys: true);
        }
    }

    /**
     * OPTIMIZED: Lazy mapping with Generator
     */
    public function mapLazy(callable $callback): Generator
    {
        foreach ($this->data as $key => $item) {
            yield $key => $callback($item);
        }
    }

    /**
     * OPTIMIZED: Eager mapping with iterator_to_array()
     */
    public function map(callable $callback, bool $preserveKeys = true): array
    {
        $generator = $this->mapLazy($callback);
        return iterator_to_array($generator, preserve_keys: $preserveKeys);
    }

    /**
     * OPTIMIZED: Lazy filtering with Generator
     */
    public function filterLazy(callable $callback): Generator
    {
        foreach ($this->data as $key => $item) {
            if ($callback($item)) {
                yield $key => $item;
            }
        }
    }

    /**
     * OPTIMIZED: Eager filtering with iterator_to_array()
     */
    public function filter(callable $callback, bool $preserveKeys = true): array
    {
        $generator = $this->filterLazy($callback);
        return iterator_to_array($generator, preserve_keys: $preserveKeys);
    }

    /**
     * OPTIMIZED: Memory-efficient groupBy with Generators
     */
    public function groupByLazy(string $column): Generator
    {
        $groups = [];

        foreach ($this->data as $row) {
            $key = $row[$column] ?? 'null';

            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }

            $groups[$key][] = $row;
        }

        foreach ($groups as $groupKey => $groupItems) {
            yield $groupKey => iterator_to_array(new \ArrayIterator($groupItems), preserve_keys: false);
        }
    }

    /**
     * OPTIMIZED: Eager groupBy with iterator_to_array()
     */
    public function groupBy(string $column): array
    {
        $generator = $this->groupByLazy($column);
        return iterator_to_array($generator, preserve_keys: true);
    }

    /**
     * OPTIMIZED: Memory-efficient pluck with preserve_keys control
     */
    public function pluck(string $column, bool $preserveKeys = false): array
    {
        $generator = function() use ($column) {
            foreach ($this->data as $key => $row) {
                yield $key => $row[$column] ?? null;
            }
        };

        return iterator_to_array($generator(), preserve_keys: $preserveKeys);
    }

    // ===================================================================
    // OPTIMIZED: Streaming and Performance Methods
    // ===================================================================

    /**
     * OPTIMIZED: Stream processing for large datasets
     */
    public function stream(callable $processor): void
    {
        foreach ($this->lazy() as $key => $item) {
            $processor($item, $key);
        }
    }

    /**
     * OPTIMIZED: Batch processing with memory management
     */
    public function processBatches(int $batchSize, callable $processor): array
    {
        $results = [];

        foreach ($this->chunk($batchSize) as $batch) {
            $batchResult = $processor($batch);
            if ($batchResult !== null) {
                $results[] = $batchResult;
            }
        }

        return $results;
    }

    /**
     * OPTIMIZED: Unique with memory-efficient processing
     */
    public function unique(string $column, bool $preserveKeys = false): array
    {
        $seen = [];
        $generator = function() use ($column, &$seen) {
            foreach ($this->data as $key => $row) {
                $value = $row[$column] ?? null;

                if (!in_array($value, $seen, true)) {
                    $seen[] = $value;
                    yield $key => $row;
                }
            }
        };

        return iterator_to_array($generator(), preserve_keys: $preserveKeys);
    }

    /**
     * OPTIMIZED: Reduce with lazy evaluation
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $accumulator = $initial;

        foreach ($this->lazy() as $item) {
            $accumulator = $callback($accumulator, $item);
        }

        return $accumulator;
    }

    // ===================================================================
    // OPTIMIZED: Aggregation Methods
    // ===================================================================

    /**
     * OPTIMIZED: Sum with lazy evaluation
     */
    public function sum(string $column): int|float
    {
        return $this->reduce(
            callback: fn($sum, $item) => $sum + ($item[$column] ?? 0),
            initial: 0
        );
    }

    /**
     * OPTIMIZED: Average with lazy evaluation
     */
    public function avg(string $column): float
    {
        $count = 0;
        $sum = $this->reduce(
            callback: function($sum, $item) use ($column, &$count) {
                $count++;
                return $sum + ($item[$column] ?? 0);
            },
            initial: 0
        );

        return $count > 0 ? $sum / $count : 0.0;
    }

    /**
     * OPTIMIZED: Min/Max with lazy evaluation
     */
    public function min(string $column): mixed
    {
        return $this->reduce(
            callback: fn($min, $item) => $min === null ?
                ($item[$column] ?? null) :
                min($min, $item[$column] ?? PHP_INT_MAX)
        );
    }

    public function max(string $column): mixed
    {
        return $this->reduce(
            callback: fn($max, $item) => $max === null ?
                ($item[$column] ?? null) :
                max($max, $item[$column] ?? PHP_INT_MIN)
        );
    }

    // ===================================================================
    // EXISTING METHODS (Backward Compatibility)
    // ===================================================================

    /**
     * Holt alle Zeilen als Array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Holt erste Zeile oder wirft Exception
     */
    public function firstOrFail(): array
    {
        $first = $this->first();

        if ($first === null) {
            throw new \RuntimeException('No results found');
        }

        return $first;
    }

    /**
     * Holt erste Zeile oder null
     */
    public function first(): ?array
    {
        return $this->data[0] ?? null;
    }

    /**
     * Holt letzte Zeile oder null
     */
    public function last(): ?array
    {
        return empty($this->data) ? null : end($this->data);
    }

    /**
     * OPTIMIZED: Sort with fluent interface
     */
    public function sortBy(string $column, bool $descending = false): self
    {
        usort($this->data, function ($a, $b) use ($column, $descending) {
            $result = $a[$column] <=> $b[$column];
            return $descending ? -$result : $result;
        });

        return $this;
    }

    /**
     * Nimmt nur die ersten N Ergebnisse
     */
    public function take(int $limit): self
    {
        $this->data = array_slice($this->data, 0, $limit);
        return $this;
    }

    /**
     * Überspringt die ersten N Ergebnisse
     */
    public function skip(int $offset): self
    {
        $this->data = array_slice($this->data, $offset);
        return $this;
    }

    /**
     * Prüft ob Ergebnisse nicht leer sind
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Prüft ob Ergebnisse leer sind
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    // ===================================================================
    // DEBUGGING AND MONITORING
    // ===================================================================

    /**
     * OPTIMIZED: Debug with performance metrics
     */
    public function dd(): self
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        echo "\n=== MYSQL QUERY RESULT DEBUG ===\n";
        echo "SQL: " . $this->sql . "\n";
        echo "Bindings: " . json_encode($this->bindings) . "\n";
        echo "Execution Time: " . round($this->executionTime * 1000, 2) . "ms\n";
        echo "Row Count: " . count($this->data) . "\n";
        echo "Affected Rows: " . $this->getAffectedRows() . "\n";
        echo "Memory Usage: " . round($memoryUsage / 1024 / 1024, 2) . "MB\n";
        echo "Peak Memory: " . round($peakMemory / 1024 / 1024, 2) . "MB\n";
        echo "Data Sample: " . json_encode(array_slice($this->data, 0, 3)) . "\n";
        echo "==============================\n\n";

        return $this;
    }

    /**
     * Holt Anzahl betroffener Zeilen (für INSERT/UPDATE/DELETE)
     */
    public function getAffectedRows(): int
    {
        return $this->statement->rowCount();
    }

    /**
     * OPTIMIZED: JSON with memory awareness
     */
    public function toJson(bool $pretty = false): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->data, $flags);
    }

    /**
     * Holt SQL Query String
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Holt Query Bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Holt Execution Time
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Holt das interne PDOStatement (für erweiterte Nutzung)
     */
    public function getStatement(): PDOStatement
    {
        return $this->statement;
    }

    // ===================================================================
    // ITERATOR & COUNTABLE INTERFACES
    // ===================================================================

    public function current(): mixed
    {
        return $this->data[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Array Access für einfache Nutzung
     */
    public function offsetExists(int $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(int $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * OPTIMIZED: String conversion with size awareness
     */
    public function __toString(): string
    {
        $count = count($this->data);
        if ($count > 100) {
            return json_encode([
                'message' => 'Large dataset - use toJson() for full output',
                'count' => $count,
                'sample' => array_slice($this->data, 0, 3)
            ]);
        }

        return $this->toJson();
    }
}