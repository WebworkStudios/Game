<?php

declare(strict_types=1);

namespace Framework\Database;

use Exception;
use Framework\Database\Enums\ConnectionType;
use Framework\Database\Enums\JoinType;
use Framework\Database\Enums\OrderDirection;
use Framework\Database\Enums\ParameterType;
use InvalidArgumentException;
use RuntimeException;

/**
 * MySQL QueryBuilder - Fluent SQL Query Builder für MySQL
 */
class QueryBuilder
{
    private string $table = '';
    private array $select = [];
    private array $joins = [];
    private array $wheres = [];
    private array $groups = [];
    private array $havings = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $bindings = [];
    private int $bindingCounter = 0;

    private bool $debugMode = false;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly MySQLGrammar      $grammar,
        private readonly string            $connectionName = 'default',
    )
    {
    }

    /**
     * Setzt Tabelle für Query
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Setzt SELECT Spalten
     */
    public function select(string ...$columns): self
    {
        $this->select = array_merge($this->select, $columns);
        return $this;
    }

    /**
     * Fügt WHERE Condition hinzu
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        // where($column, $value) syntax - nur 2 Parameter
        if ($value === null && func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $binding = $this->createBinding($column);

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => (string)$operator,
            'binding' => $binding,
        ];

        $this->bindings[$binding] = $value;

        return $this;
    }

    /**
     * WHERE IN Condition
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // WHERE 1=0 für leere Arrays (kein Match)
            return $this->whereRaw('1 = 0');
        }

        $bindings = [];
        foreach ($values as $index => $value) {
            $binding = $this->createBinding("{$column}_in_{$index}");
            $bindings[] = $binding;
            $this->bindings[$binding] = $value;
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'bindings' => $bindings,
        ];

        return $this;
    }

    /**
     * WHERE NOT IN Condition
     */
    public function whereNotIn(string $column, array $values): self
    {
        if (empty($values)) {
            // WHERE 1=1 für leere Arrays (alle Matches)
            return $this->whereRaw('1 = 1');
        }

        $bindings = [];
        foreach ($values as $index => $value) {
            $binding = $this->createBinding("{$column}_not_in_{$index}");
            $bindings[] = $binding;
            $this->bindings[$binding] = $value;
        }

        $this->wheres[] = [
            'type' => 'not_in',
            'column' => $column,
            'bindings' => $bindings,
        ];

        return $this;
    }

    /**
     * WHERE IS NULL Condition
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'not' => false,
        ];

        return $this;
    }

    /**
     * WHERE IS NOT NULL Condition
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'not' => true,
        ];

        return $this;
    }

    /**
     * WHERE LIKE Condition
     */
    public function whereLike(string $column, string $value): self
    {
        return $this->where($column, 'LIKE', $value);
    }

    /**
     * WHERE BETWEEN Condition
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $minBinding = $this->createBinding("{$column}_min");
        $maxBinding = $this->createBinding("{$column}_max");

        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'min_binding' => $minBinding,
            'max_binding' => $maxBinding,
        ];

        $this->bindings[$minBinding] = $min;
        $this->bindings[$maxBinding] = $max;

        return $this;
    }

    /**
     * Raw WHERE Condition
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
        ];

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * INNER JOIN
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin(JoinType::INNER, $table, $first, $operator, $second);
    }

    /**
     * LEFT JOIN
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin(JoinType::LEFT, $table, $first, $operator, $second);
    }

    /**
     * RIGHT JOIN
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin(JoinType::RIGHT, $table, $first, $operator, $second);
    }

    /**
     * FULL OUTER JOIN
     */
    public function fullJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin(JoinType::FULL, $table, $first, $operator, $second);
    }

    /**
     * Fügt JOIN hinzu
     */
    private function addJoin(JoinType $type, string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * GROUP BY
     */
    public function groupBy(string ...$columns): self
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    /**
     * HAVING Condition
     */
    public function having(string $column, string $operator, mixed $value): self
    {
        $binding = $this->createBinding($column);

        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'binding' => $binding,
        ];

        $this->bindings[$binding] = $value;

        return $this;
    }

    /**
     * ORDER BY ASC
     */
    public function orderBy(string $column, OrderDirection $direction = OrderDirection::ASC): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * ORDER BY DESC
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, OrderDirection::DESC);
    }

    /**
     * LIMIT
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * OFFSET
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Kombiniert LIMIT und OFFSET für Pagination
     */
    public function paginate(int $perPage, int $page = 1): self
    {
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;
        return $this;
    }

    /**
     * Führt SELECT Query aus
     */
    public function get(): QueryResult
    {
        $components = [
            'select' => empty($this->select) ? ['*'] : $this->select,
            'from' => $this->table,
            'joins' => $this->joins,
            'wheres' => $this->wheres,
            'groups' => $this->groups,
            'havings' => $this->havings,
            'orders' => $this->orders,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];

        $sql = $this->grammar->compileSelect($components);

        return $this->executeQuery($sql, ConnectionType::READ);
    }

    /**
     * Holt erste Zeile
     */
    public function first(): ?array
    {
        return $this->limit(1)->get()->first();
    }

    /**
     * Holt erste Zeile oder Exception
     */
    public function firstOrFail(): array
    {
        return $this->limit(1)->get()->firstOrFail();
    }

    /**
     * Zählt Ergebnisse
     */
    public function count(): int
    {
        $original = $this->select;
        $this->select = ['COUNT(*) as count'];

        $result = $this->get()->first();
        $this->select = $original;

        return (int)($result['count'] ?? 0);
    }

    /**
     * Prüft ob Ergebnisse existieren
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * INSERT Query
     */
    public function insert(array $values): bool
    {
        if (empty($values)) {
            throw new InvalidArgumentException('Insert values cannot be empty');
        }

        $sql = $this->grammar->compileInsert($this->table, $values);
        $bindings = $this->prepareInsertBindings($values);

        $result = $this->executeQuery($sql, ConnectionType::WRITE, $bindings);

        return $result->getAffectedRows() > 0;
    }

    /**
     * INSERT und holt letzte ID
     */
    public function insertGetId(array $values): int
    {
        if ($this->insert($values)) {
            $connection = $this->connectionManager->getWriteConnection($this->connectionName);
            return (int)$connection->lastInsertId();
        }

        throw new RuntimeException('Failed to insert record');
    }

    /**
     * UPDATE Query
     */
    public function update(array $values): int
    {
        if (empty($values)) {
            throw new InvalidArgumentException('Update values cannot be empty');
        }

        $sql = $this->grammar->compileUpdate($this->table, $values, $this->wheres);
        $bindings = array_merge($values, $this->bindings);

        $result = $this->executeQuery($sql, ConnectionType::WRITE, $bindings);

        return $result->getAffectedRows();
    }

    /**
     * DELETE Query
     */
    public function delete(): int
    {
        $sql = $this->grammar->compileDelete($this->table, $this->wheres);

        $result = $this->executeQuery($sql, ConnectionType::WRITE, $this->bindings);

        return $result->getAffectedRows();
    }

    /**
     * TRUNCATE Table
     */
    public function truncate(): bool
    {
        $sql = "TRUNCATE TABLE " . $this->grammar->wrapTable($this->table);

        $result = $this->executeQuery($sql, ConnectionType::WRITE, []);

        return $result->getAffectedRows() >= 0;
    }

    /**
     * Erstellt eindeutigen Binding-Namen
     */
    private function createBinding(string $column): string
    {
        $base = preg_replace('/[^a-zA-Z0-9_]/', '_', $column);
        return $base . '_' . ++$this->bindingCounter;
    }

    /**
     * Bereitet Insert-Bindings vor
     */
    private function prepareInsertBindings(array $values): array
    {
        // Multiple rows
        if (isset($values[0]) && is_array($values[0])) {
            $bindings = [];
            foreach ($values as $index => $row) {
                foreach ($row as $column => $value) {
                    $bindings["{$column}_{$index}"] = $value;
                }
            }
            return $bindings;
        }

        // Single row
        return $values;
    }

    /**
     * Führt Query aus
     */
    private function executeQuery(string $sql, ConnectionType $type, ?array $bindings = null): QueryResult
    {
        $bindings ??= $this->bindings;

        $connection = $this->connectionManager->getConnection($this->connectionName, $type);

        $startTime = microtime(true);

        try {
            $statement = $connection->prepare($sql);

            // Bind parameters mit Typ-Erkennung
            foreach ($bindings as $key => $value) {
                $paramType = ParameterType::fromValue($value);
                $statement->bindValue(":{$key}", $value, $paramType->value);
            }

            $statement->execute();

            $executionTime = microtime(true) - $startTime;

            if ($this->debugMode) {
                $this->debugQuery($sql, $bindings, $executionTime);
            }

            return new QueryResult($statement, $sql, $bindings, $executionTime);

        } catch (Exception $e) {
            if ($this->debugMode) {
                $this->debugQuery($sql, $bindings, microtime(true) - $startTime, $e);
            }

            throw $e;
        }
    }

    /**
     * Debug Query Output
     */
    private function debugQuery(string $sql, array $bindings, float $time, ?Exception $error = null): void
    {
        echo "\n=== MYSQL QUERY DEBUG ===\n";
        echo "SQL: {$sql}\n";
        echo "Bindings: " . json_encode($bindings) . "\n";
        echo "Time: " . round($time * 1000, 2) . "ms\n";
        echo "Connection: {$this->connectionName}\n";

        if ($error) {
            echo "Error: " . $error->getMessage() . "\n";
        }

        echo "========================\n\n";
    }

    /**
     * Setzt Debug-Modus
     */
    public function debug(bool $enabled = true): self
    {
        $this->debugMode = $enabled;
        return $this;
    }

    /**
     * Holt Raw SQL für Debugging
     */
    public function toSql(): string
    {
        $components = [
            'select' => empty($this->select) ? ['*'] : $this->select,
            'from' => $this->table,
            'joins' => $this->joins,
            'wheres' => $this->wheres,
            'groups' => $this->groups,
            'havings' => $this->havings,
            'orders' => $this->orders,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];

        return $this->grammar->compileSelect($components);
    }

    /**
     * Holt alle Bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Holt Connection Name
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Klont QueryBuilder für neue Query
     */
    public function newQuery(): self
    {
        return new self(
            $this->connectionManager,
            $this->grammar,
            $this->connectionName
        );
    }

    /**
     * Reset Query Builder
     */
    public function reset(): self
    {
        $this->table = '';
        $this->select = [];
        $this->joins = [];
        $this->wheres = [];
        $this->groups = [];
        $this->havings = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        $this->bindings = [];
        $this->bindingCounter = 0;

        return $this;
    }

    /**
     * Führt Callback in MySQL Transaction aus
     */
    public function transaction(callable $callback): mixed
    {
        return $this->connectionManager->transaction($callback, $this->connectionName);
    }

    /**
     * Raw SQL Query ausführen
     */
    public function raw(string $sql, array $bindings = []): QueryResult
    {
        return $this->executeQuery($sql, ConnectionType::READ, $bindings);
    }
}