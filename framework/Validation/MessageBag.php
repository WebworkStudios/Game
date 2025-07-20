<?php

declare(strict_types=1);

namespace Framework\Validation;

use Countable;
use Iterator;
use JsonException;
use Generator;

/**
 * MessageBag - OPTIMIZED with PHP 8.4 iterator_to_array() Features
 *
 * PHASE 1 OPTIMIZATIONS:
 * ✅ Memory-efficient flatten() with Generators
 * ✅ Explicit preserve_keys control with iterator_to_array()
 * ✅ Lazy iteration for large validation sets
 * ✅ Streaming validation error processing
 * ✅ Type-safe array conversions
 */
class MessageBag implements Countable, Iterator
{
    /** @var array<string, array<string>> */
    private array $messages = [];
    private int $position = 0;

    // ===================================================================
    // OPTIMIZED: Core Collection Methods with iterator_to_array()
    // ===================================================================

    /**
     * OPTIMIZED: Convert to array with explicit key preservation control
     */
    public function toArray(bool $preserveKeys = true): array
    {
        return iterator_to_array($this, preserve_keys: $preserveKeys);
    }

    /**
     * OPTIMIZED: Memory-efficient flatten with Generator + iterator_to_array()
     */
    public function flatten(bool $preserveKeys = false): array
    {
        $generator = function() {
            foreach ($this->messages as $fieldMessages) {
                foreach ($fieldMessages as $message) {
                    yield $message;
                }
            }
        };

        return iterator_to_array($generator(), preserve_keys: $preserveKeys);
    }

    /**
     * OPTIMIZED: Lazy iteration over all field messages
     */
    public function lazy(): Generator
    {
        foreach ($this->messages as $field => $messages) {
            foreach ($messages as $message) {
                yield $field => $message;
            }
        }
    }

    /**
     * OPTIMIZED: Field messages as Generator
     */
    public function getAsGenerator(string $field): Generator
    {
        foreach ($this->messages[$field] ?? [] as $message) {
            yield $message;
        }
    }

    /**
     * OPTIMIZED: All messages as Iterator with field context
     */
    public function getAllAsIterator(): Generator
    {
        foreach ($this->messages as $field => $messages) {
            foreach ($messages as $index => $message) {
                yield "{$field}.{$index}" => [
                    'field' => $field,
                    'message' => $message,
                    'index' => $index
                ];
            }
        }
    }

    /**
     * OPTIMIZED: Stream processing for large validation sets
     */
    public function stream(callable $processor): void
    {
        foreach ($this->lazy() as $field => $message) {
            $processor($message, $field);
        }
    }

    /**
     * OPTIMIZED: Filter messages with lazy evaluation
     */
    public function filterLazy(callable $callback): Generator
    {
        foreach ($this->messages as $field => $messages) {
            foreach ($messages as $message) {
                if ($callback($message, $field)) {
                    yield $field => $message;
                }
            }
        }
    }

    /**
     * OPTIMIZED: Filter messages with iterator_to_array()
     */
    public function filter(callable $callback, bool $preserveKeys = true): array
    {
        $generator = $this->filterLazy($callback);
        return iterator_to_array($generator, preserve_keys: $preserveKeys);
    }

    /**
     * OPTIMIZED: Map messages with lazy evaluation
     */
    public function mapLazy(callable $callback): Generator
    {
        foreach ($this->messages as $field => $messages) {
            foreach ($messages as $message) {
                yield $field => $callback($message, $field);
            }
        }
    }

    /**
     * OPTIMIZED: Map messages with iterator_to_array()
     */
    public function map(callable $callback, bool $preserveKeys = true): array
    {
        $generator = $this->mapLazy($callback);
        return iterator_to_array($generator, preserve_keys: $preserveKeys);
    }

    // ===================================================================
    // OPTIMIZED: Advanced Collection Operations
    // ===================================================================

    /**
     * OPTIMIZED: Group messages by field with lazy evaluation
     */
    public function groupByFieldLazy(): Generator
    {
        foreach ($this->messages as $field => $messages) {
            yield $field => iterator_to_array(
                new \ArrayIterator($messages),
                preserve_keys: false
            );
        }
    }

    /**
     * OPTIMIZED: Chunk messages for batch processing
     */
    public function chunk(int $size): Generator
    {
        if ($size <= 0) {
            throw new \InvalidArgumentException('Chunk size must be positive');
        }

        $chunk = [];
        $count = 0;

        foreach ($this->lazy() as $field => $message) {
            $chunk[$field] = $message;
            $count++;

            if ($count >= $size) {
                yield iterator_to_array(new \ArrayIterator($chunk), preserve_keys: true);
                $chunk = [];
                $count = 0;
            }
        }

        if (!empty($chunk)) {
            yield iterator_to_array(new \ArrayIterator($chunk), preserve_keys: true);
        }
    }

    /**
     * OPTIMIZED: Batch processing for large validation sets
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
     * OPTIMIZED: Reduce messages with lazy evaluation
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $accumulator = $initial;

        foreach ($this->lazy() as $field => $message) {
            $accumulator = $callback($accumulator, $message, $field);
        }

        return $accumulator;
    }

    // ===================================================================
    // OPTIMIZED: Validation-Specific Methods
    // ===================================================================

    /**
     * OPTIMIZED: Get unique messages across all fields
     */
    public function unique(bool $preserveKeys = false): array
    {
        $seen = [];
        $generator = function() use (&$seen) {
            foreach ($this->lazy() as $field => $message) {
                if (!in_array($message, $seen, true)) {
                    $seen[] = $message;
                    yield $field => $message;
                }
            }
        };

        return iterator_to_array($generator(), preserve_keys: $preserveKeys);
    }

    /**
     * OPTIMIZED: Get messages by severity (if formatted appropriately)
     */
    public function getBySeverity(string $severity): array
    {
        $generator = function() use ($severity) {
            foreach ($this->messages as $field => $messages) {
                foreach ($messages as $message) {
                    // Simple severity detection by keywords
                    if (str_contains(strtolower($message), strtolower($severity))) {
                        yield $field => $message;
                    }
                }
            }
        };

        return iterator_to_array($generator(), preserve_keys: true);
    }

    /**
     * OPTIMIZED: Count messages per field lazily
     */
    public function countPerField(): array
    {
        $generator = function() {
            foreach ($this->messages as $field => $messages) {
                yield $field => count($messages);
            }
        };

        return iterator_to_array($generator(), preserve_keys: true);
    }

    /**
     * OPTIMIZED: Get fields with most errors
     */
    public function getFieldsByErrorCount(bool $descending = true): array
    {
        $counts = $this->countPerField();

        if ($descending) {
            arsort($counts);
        } else {
            asort($counts);
        }

        return $counts;
    }

    // ===================================================================
    // EXISTING METHODS (Backward Compatibility)
    // ===================================================================

    /**
     * Error-Message für Feld hinzufügen
     */
    public function add(string $field, string $message): void
    {
        $this->messages[$field] ??= [];
        $this->messages[$field][] = $message;
    }

    /**
     * Erste Message für ein Feld abrufen
     */
    public function first(string $field): ?string
    {
        return $this->messages[$field][0] ?? null;
    }

    /**
     * Alle Messages für ein Feld abrufen
     *
     * @return array<string>
     */
    public function get(string $field): array
    {
        return $this->messages[$field] ?? [];
    }

    /**
     * Alle Messages abrufen
     *
     * @return array<string, array<string>>
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * Prüfen ob MessageBag leer ist
     */
    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    /**
     * Prüfen ob Feld Errors hat
     */
    public function has(string $field): bool
    {
        return isset($this->messages[$field]) && $this->messages[$field] !== [];
    }

    // ===================================================================
    // OPTIMIZED: JSON and Serialization
    // ===================================================================

    /**
     * OPTIMIZED: JSON conversion with performance awareness
     *
     * @throws JsonException
     */
    public function toJson(bool $pretty = false): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->toArray(), $flags);
    }

    /**
     * OPTIMIZED: Export for debugging with size awareness
     */
    public function toDebugArray(): array
    {
        $totalMessages = $this->count();
        $fieldCount = count($this->messages);

        return [
            'summary' => [
                'total_messages' => $totalMessages,
                'field_count' => $fieldCount,
                'memory_usage' => memory_get_usage(true),
            ],
            'fields' => $this->countPerField(),
            'messages' => $totalMessages > 100 ?
                array_slice($this->all(), 0, 10) :
                $this->all(),
            'sample_flat' => array_slice($this->flatten(), 0, 5),
        ];
    }

    // ===================================================================
    // ITERATOR & COUNTABLE INTERFACES
    // ===================================================================

    /**
     * OPTIMIZED: Count with lazy evaluation
     */
    public function count(): int
    {
        return $this->reduce(
            callback: fn($count, $message, $field) => $count + 1,
            initial: 0
        );
    }

    /**
     * OPTIMIZED: Iterator implementation
     */
    public function current(): mixed
    {
        $keys = array_keys($this->messages);
        $key = $keys[$this->position] ?? null;
        return $key !== null ? $this->messages[$key] : null;
    }

    public function key(): mixed
    {
        $keys = array_keys($this->messages);
        return $keys[$this->position] ?? null;
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
        $keys = array_keys($this->messages);
        return isset($keys[$this->position]);
    }

    // ===================================================================
    // DEBUGGING AND MONITORING
    // ===================================================================

    /**
     * OPTIMIZED: Debug dump with performance metrics
     */
    public function dd(): self
    {
        $debugInfo = $this->toDebugArray();

        echo "\n=== MESSAGE BAG DEBUG ===\n";
        echo "Total Messages: " . $debugInfo['summary']['total_messages'] . "\n";
        echo "Field Count: " . $debugInfo['summary']['field_count'] . "\n";
        echo "Fields by Error Count: " . json_encode($this->getFieldsByErrorCount()) . "\n";
        echo "Sample Messages: " . json_encode($debugInfo['sample_flat']) . "\n";
        echo "========================\n\n";

        return $this;
    }

    /**
     * OPTIMIZED: String conversion with size awareness
     * @throws JsonException
     */
    public function __toString(): string
    {
        $count = $this->count();

        if ($count > 50) {
            return json_encode([
                'message' => 'Large validation error set - use toJson() for full output',
                'total_messages' => $count,
                'field_count' => count($this->messages),
                'sample' => array_slice($this->flatten(), 0, 5)
            ]);
        }

        return $this->toJson();
    }
}