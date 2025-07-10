<?php


declare(strict_types=1);

namespace Framework\Validation;

use Countable;
use Iterator;

/**
 * MessageBag - Collection for validation error messages
 */
class MessageBag implements Countable, Iterator
{
    private array $messages = [];
    private int $position = 0;

    /**
     * Add error message for field
     */
    public function add(string $field, string $message): void
    {
        if (!isset($this->messages[$field])) {
            $this->messages[$field] = [];
        }

        $this->messages[$field][] = $message;
    }

    /**
     * Get first message for a field
     */
    public function first(string $field): ?string
    {
        $messages = $this->get($field);
        return $messages[0] ?? null;
    }

    /**
     * Get all messages for a field
     */
    public function get(string $field): array
    {
        return $this->messages[$field] ?? [];
    }

    /**
     * Get all messages
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * Check if bag is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->messages);
    }

    /**
     * Check if field has errors
     */
    public function has(string $field): bool
    {
        return isset($this->messages[$field]) && !empty($this->messages[$field]);
    }

    /**
     * Get all messages as single array
     */
    public function flatten(): array
    {
        $flat = [];
        foreach ($this->messages as $messages) {
            $flat = array_merge($flat, $messages);
        }
        return $flat;
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Get messages as flat array
     */
    public function toArray(): array
    {
        $flat = [];
        foreach ($this->messages as $field => $messages) {
            $flat[$field] = $messages;
        }
        return $flat;
    }

    // Countable interface

    public function count(): int
    {
        return array_sum(array_map('count', $this->messages));
    }

    // Iterator interface
    public function current(): mixed
    {
        $keys = array_keys($this->messages);
        $key = $keys[$this->position] ?? null;
        return $key ? $this->messages[$key] : null;
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
}