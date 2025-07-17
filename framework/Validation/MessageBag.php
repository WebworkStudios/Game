<?php
declare(strict_types=1);

namespace Framework\Validation;

use Countable;
use Iterator;
use JsonException;

/**
 * MessageBag - Collection für Validation Error Messages
 *
 * MODERNISIERUNGEN:
 * ✅ Array shapes für bessere Type Safety
 * ✅ JSON_THROW_ON_ERROR Flag
 * ✅ Nullable return types wo sinnvoll
 * ✅ Bessere Iterator-Implementation
 */
class MessageBag implements Countable, Iterator
{
    /** @var array<string, array<string>> */
    private array $messages = [];
    private int $position = 0;

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

    /**
     * Alle Messages als flaches Array
     *
     * @return array<string>
     */
    public function flatten(): array
    {
        return array_merge(...array_values($this->messages));
    }

    /**
     * Als JSON konvertieren
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Messages als Array strukturiert
     *
     * @return array<string, array<string>>
     */
    public function toArray(): array
    {
        return $this->messages;
    }

    // Countable Interface
    public function count(): int
    {
        return array_sum(array_map('count', $this->messages));
    }

    // Iterator Interface
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
}