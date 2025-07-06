<?php
/**
 * Optimized Query Builder with Scope Support
 * Fluent query builder with consolidated methods and reduced duplication
 */
declare(strict_types=1);

namespace Framework\Database;

use InvalidArgumentException;
use PDO;

class QueryBuilder
{
    private PDO $connection;
    private string $table;
    private array $select = ['*'];
    private array $where = [];
    private array $joins = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private array $having = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $bindings = [];
    private array $scopes = [];

    // WHERE clause type constants
    private const WHERE_BASIC = 'where';
    private const WHERE_IN = 'whereIn';
    private const WHERE_NOT_IN = 'whereNotIn';
    private const WHERE_NULL = 'whereNull';
    private const WHERE_NOT_NULL = 'whereNotNull';
    private const WHERE_NESTED = 'nested';

    // JOIN type constants
    private const JOIN_INNER = 'INNER';
    private const JOIN_LEFT = 'LEFT';
    private const JOIN_RIGHT = 'RIGHT';

    public function __construct(PDO $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Set SELECT fields
     */
    public function select(string|array $fields): self
    {
        $this->select = is_string($fields) ? [$fields] : $fields;
        return $this;
    }

    /**
     * Add WHERE condition
     */
    public function where(string|callable $column, string|int|float|null $operator = null, mixed $value = null): self
    {
        return $this->addWhereCondition(self::WHERE_BASIC, $column, $operator, $value, 'AND');
    }

    /**
     * Add OR WHERE condition
     */
    public function orWhere(string $column, string $operator, mixed $value = null): self
    {
        return $this->addWhereCondition(self::WHERE_BASIC, $column, $operator, $value, 'OR');
    }

    /**
     * Add WHERE IN condition
     */
    public function whereIn(string $column, array $values): self
    {
        return $this->addWhereCondition(self::WHERE_IN, $column, null, $values, 'AND');
    }

    /**
     * Add WHERE NOT IN condition
     */
    public function whereNotIn(string $column, array $values): self
    {
        return $this->addWhereCondition(self::WHERE_NOT_IN, $column, null, $values, 'AND');
    }

    /**
     * Add WHERE NULL condition
     */
    public function whereNull(string $column): self
    {
        return $this->addWhereCondition(self::WHERE_NULL, $column, null, null, 'AND');
    }

    /**
     * Add WHERE NOT NULL condition
     */
    public function whereNotNull(string $column): self
    {
        return $this->addWhereCondition(self::WHERE_NOT_NULL, $column, null, null, 'AND');
    }

    /**
     * Consolidated method for adding WHERE conditions
     */
    private function addWhereCondition(string $type, string|callable $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        // Handle callable for nested conditions
        if (is_callable($column)) {
            $nestedQuery = new static($this->connection, $this->table);
            $column($nestedQuery);

            if (!empty($nestedQuery->where)) {
                $this->where[] = [
                    'type' => self::WHERE_NESTED,
                    'query' => $nestedQuery,
                    'boolean' => $boolean
                ];
            }
            return $this;
        }

        // Handle basic WHERE conditions
        if ($type === self::WHERE_BASIC) {
            // Handle two-parameter syntax: where('column', 'value')
            if ($operator !== null && $value === null) {
                $value = $operator;
                $operator = '=';
            }

            if ($value === null) {
                throw new InvalidArgumentException('WHERE clause requires a value');
            }

            $this->where[] = [
                'type' => $type,
                'column' => $column,
                'operator' => (string)$operator,
                'value' => $value,
                'boolean' => $boolean
            ];
        } else {
            // Handle special WHERE conditions (IN, NOT IN, NULL, NOT NULL)
            $this->where[] = [
                'type' => $type,
                'column' => $column,
                'values' => $value, // For IN/NOT IN operations
                'boolean' => $boolean
            ];
        }

        return $this;
    }

    /**
     * Add INNER JOIN clause
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin(self::JOIN_INNER, $table, $first, $operator, $second);
    }

    /**
     * Add LEFT JOIN clause
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin(self::JOIN_LEFT, $table, $first, $operator, $second);
    }

    /**
     * Add RIGHT JOIN clause
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin(self::JOIN_RIGHT, $table, $first, $operator, $second);
    }

    /**
     * Consolidated method for adding JOIN clauses
     */
    private function addJoin(string $type, string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * Add ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];

        return $this;
    }

    /**
     * Add GROUP BY clause
     */
    public function groupBy(string|array $columns): self
    {
        $columns = is_string($columns) ? [$columns] : $columns;
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    /**
     * Add HAVING clause
     */
    public function having(string $column, string $operator, mixed $value): self
    {
        $this->having[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Set LIMIT clause
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set OFFSET clause
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Apply scope to query
     */
    public function scope(string $name, ...$args): self
    {
        if (!isset($this->scopes[$name])) {
            throw new InvalidArgumentException("Scope [{$name}] not found");
        }

        $scope = $this->scopes[$name];
        $scope($this, ...$args);

        return $this;
    }

    /**
     * Register a scope
     */
    public function addScope(string $name, callable $scope): self
    {
        $this->scopes[$name] = $scope;
        return $this;
    }

    /**
     * Get record by ID
     */
    public function find(int $id): ?array
    {
        return $this->where('id', $id)->first();
    }

    /**
     * Get first record
     */
    public function first(): ?array
    {
        $originalLimit = $this->limit;
        $this->limit = 1;

        $result = $this->get();
        $this->limit = $originalLimit;

        return $result[0] ?? null;
    }

    /**
     * Get all records
     */
    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($this->bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count records
     */
    public function count(): int
    {
        $originalSelect = $this->select;
        $this->select = ['COUNT(*) as count'];

        $sql = $this->buildSelectQuery();
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($this->bindings);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->select = $originalSelect;

        return (int)$result['count'];
    }

    /**
     * Build SELECT query
     */
    private function buildSelectQuery(): string
    {
        $this->bindings = []; // Reset bindings

        $sql = "SELECT " . implode(', ', $this->select) . " FROM {$this->table}";

        // Add JOINs
        $sql .= $this->buildJoinClause();

        // Add WHERE
        $whereClause = $this->buildWhereClause();
        $sql .= $whereClause['sql'];
        $this->bindings = array_merge($this->bindings, $whereClause['bindings']);

        // Add GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        // Add HAVING
        if (!empty($this->having)) {
            $sql .= $this->buildHavingClause();
        }

        // Add ORDER BY
        if (!empty($this->orderBy)) {
            $sql .= $this->buildOrderByClause();
        }

        // Add LIMIT and OFFSET
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    /**
     * Build JOIN clause
     */
    private function buildJoinClause(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $joinClauses = [];
        foreach ($this->joins as $join) {
            $joinClauses[] = " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        return implode('', $joinClauses);
    }

    /**
     * Build WHERE clause - Optimized version
     */
    private function buildWhereClause(): array
    {
        if (empty($this->where)) {
            return ['sql' => '', 'bindings' => []];
        }

        $clauses = [];
        $bindings = [];

        foreach ($this->where as $index => $condition) {
            $boolean = $index === 0 ? '' : " {$condition['boolean']} ";

            switch ($condition['type']) {
                case self::WHERE_BASIC:
                    $clauses[] = $boolean . "`{$condition['column']}` {$condition['operator']} ?";
                    $bindings[] = $condition['value'];
                    break;

                case self::WHERE_IN:
                    $placeholders = $this->createPlaceholders(count($condition['values']));
                    $clauses[] = $boolean . "`{$condition['column']}` IN ({$placeholders})";
                    $bindings = array_merge($bindings, $condition['values']);
                    break;

                case self::WHERE_NOT_IN:
                    $placeholders = $this->createPlaceholders(count($condition['values']));
                    $clauses[] = $boolean . "`{$condition['column']}` NOT IN ({$placeholders})";
                    $bindings = array_merge($bindings, $condition['values']);
                    break;

                case self::WHERE_NULL:
                    $clauses[] = $boolean . "`{$condition['column']}` IS NULL";
                    break;

                case self::WHERE_NOT_NULL:
                    $clauses[] = $boolean . "`{$condition['column']}` IS NOT NULL";
                    break;

                case self::WHERE_NESTED:
                    $nestedClause = $condition['query']->buildWhereClause();
                    if (!empty($nestedClause['sql'])) {
                        $nestedSql = $nestedClause['sql'];
                        if (str_starts_with($nestedSql, ' WHERE ')) {
                            $nestedSql = substr($nestedSql, 7);
                        }
                        $clauses[] = $boolean . '(' . $nestedSql . ')';
                        $bindings = array_merge($bindings, $nestedClause['bindings']);
                    }
                    break;
            }
        }

        $sql = empty($clauses) ? '' : ' WHERE ' . implode('', $clauses);

        return ['sql' => $sql, 'bindings' => $bindings];
    }

    /**
     * Build HAVING clause
     */
    private function buildHavingClause(): string
    {
        $havingClauses = [];
        foreach ($this->having as $having) {
            $havingClauses[] = "{$having['column']} {$having['operator']} ?";
            $this->bindings[] = $having['value'];
        }
        return " HAVING " . implode(' AND ', $havingClauses);
    }

    /**
     * Build ORDER BY clause
     */
    private function buildOrderByClause(): string
    {
        $orderClauses = [];
        foreach ($this->orderBy as $order) {
            $orderClauses[] = "{$order['column']} {$order['direction']}";
        }
        return " ORDER BY " . implode(', ', $orderClauses);
    }

    /**
     * Create placeholders for IN clauses
     */
    private function createPlaceholders(int $count): string
    {
        return implode(', ', array_fill(0, $count, '?'));
    }

    /**
     * Insert record
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = $this->createPlaceholders(count($columns));

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(array_values($data));

        return (int)$this->connection->lastInsertId();
    }

    /**
     * Batch insert records
     */
    public function insertBatch(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $columns = array_keys($data[0]);
        $singlePlaceholder = '(' . $this->createPlaceholders(count($columns)) . ')';
        $allPlaceholders = array_fill(0, count($data), $singlePlaceholder);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES " . implode(', ', $allPlaceholders);

        $values = [];
        foreach ($data as $row) {
            $values = array_merge($values, array_values($row));
        }

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Update records
     */
    public function update(array $data): int
    {
        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);

        if (!empty($this->where)) {
            $whereClause = $this->buildWhereClause();
            $sql .= $whereClause['sql'];
            $bindings = array_merge($bindings, $whereClause['bindings']);
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        $bindings = [];

        if (!empty($this->where)) {
            $whereClause = $this->buildWhereClause();
            $sql .= $whereClause['sql'];
            $bindings = $whereClause['bindings'];
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * Get the built SQL query (for debugging)
     */
    public function toSql(): string
    {
        return $this->buildSelectQuery();
    }

    /**
     * Get query bindings (for debugging)
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}