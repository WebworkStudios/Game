<?php


declare(strict_types=1);

namespace Framework\Database;

use Countable;
use Iterator;
use PDOStatement;

/**
 * Query Result - Wrapper für Datenbankabfrage-Ergebnisse
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
            $grouped[$key][] = $row;
        }

        return $grouped;
    }

    /**
     * Holt eindeutige Werte einer Spalte
     */
    public function pluck(string $column): array
    {
        return array_column($this->data, $column);
    }

    /**
     * Erstellt Key-Value Array aus zwei Spalten
     */
    public function keyBy(string $keyColumn, string $valueColumn): array
    {
        $result = [];

        foreach ($this->data as $row) {
            $result[$row[$keyColumn]] = $row[$valueColumn];
        }

        return $result;
    }

    /**
     * Paginiert Ergebnisse
     */
    public function paginate(int $perPage, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($this->data, $offset, $perPage),
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => count($this->data),
            'last_page' => (int)ceil(count($this->data) / $perPage),
            'has_more' => $offset + $perPage < count($this->data),
        ];
    }

    /**
     * Prüft ob Ergebnisse leer sind
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Holt Ausführungszeit in Millisekunden
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Holt SQL-Query
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Holt Parameter-Bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Holt Query-Informationen für Debugging
     */
    public function getQueryInfo(): array
    {
        return [
            'sql' => $this->sql,
            'bindings' => $this->bindings,
            'execution_time_ms' => round($this->executionTime * 1000, 2),
            'row_count' => count($this->data),
            'memory_usage' => memory_get_usage(true),
        ];
    }

    /**
     * Debug-Output für Query
     */
    public function dump(): self
    {
        echo "Query Result Debug:\n";
        echo "SQL: {$this->sql}\n";
        echo "Bindings: " . json_encode($this->bindings) . "\n";
        echo "Execution Time: " . round($this->executionTime * 1000, 2) . "ms\n";
        echo "Row Count: " . count($this->data) . "\n";
        echo "Data: " . $this->toJson() . "\n\n";

        return $this;
    }

    /**
     * Erstellt JSON-String der Ergebnisse
     */
    public function toJson(): string
    {
        return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
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