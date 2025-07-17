<?php

declare(strict_types=1);

namespace Framework\Database;

use Countable;
use Iterator;
use PDOStatement;

/**
 * Query Result - Wrapper für MySQL-Datenbankabfrage-Ergebnisse
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
     * Mappt Ergebnisse durch Callback
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->data);
    }

    /**
     * Filtert Ergebnisse durch Callback
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->data, $callback);
    }

    /**
     * Gruppiert Ergebnisse nach Spalte
     */
    public function groupBy(string $column): array
    {
        $grouped = [];

        foreach ($this->data as $row) {
            $key = $row[$column] ?? 'null';
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $row;
        }

        return $grouped;
    }

    /**
     * Sortiert Ergebnisse nach Spalte
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
     * Pluck - Extrahiert nur eine Spalte
     */
    public function pluck(string $column): array
    {
        return array_column($this->data, $column);
    }

    /**
     * Unique - Entfernt Duplikate
     */
    public function unique(string $column): self
    {
        $seen = [];
        $unique = [];

        foreach ($this->data as $row) {
            $value = $row[$column] ?? null;
            if (!in_array($value, $seen, true)) {
                $seen[] = $value;
                $unique[] = $row;
            }
        }

        $this->data = $unique;
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

    /**
     * Debug-Ausgabe für MySQL Query Result
     */
    public function dd(): self
    {
        echo "\n=== MYSQL QUERY RESULT DEBUG ===\n";
        echo "SQL: " . $this->sql . "\n";
        echo "Bindings: " . json_encode($this->bindings) . "\n";
        echo "Execution Time: " . round($this->executionTime * 1000, 2) . "ms\n";
        echo "Row Count: " . count($this->data) . "\n";
        echo "Affected Rows: " . $this->getAffectedRows() . "\n";
        echo "Data: " . $this->toJson() . "\n";
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
     * Erstellt JSON-String der Ergebnisse
     */
    public function toJson(): string
    {
        return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
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

    // Iterator Interface

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

    // Countable Interface
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
     * Konvertiert zu Array bei String-Cast
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}