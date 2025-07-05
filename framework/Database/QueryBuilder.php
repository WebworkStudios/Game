<?php

/**
 * Query Builder with Scope Support
 * Fluent query builder with support for multiple database connections and scopes
 *
 * File: framework/Database/QueryBuilder.php
 * Directory: /framework/Database/
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
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $this->select = $fields;
        return $this;
    }

    /**
     * Add OR WHERE condition
     */
    public function orWhere(string $column, string $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'type' => 'where',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        return $this;
    }

    /**
     * Add WHERE IN condition
     */
    public function whereIn(string $column, array $values): self
    {
        $this->where[] = [
            'type' => 'whereIn',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add WHERE NOT IN condition
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->where[] = [
            'type' => 'whereNotIn',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add WHERE NULL condition
     */
    public function whereNull(string $column): self
    {
        $this->where[] = [
            'type' => 'whereNull',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add WHERE NOT NULL condition
     */
    public function whereNotNull(string $column): self
    {
        $this->where[] = [
            'type' => 'whereNotNull',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add JOIN clause
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * Add LEFT JOIN clause
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * Add RIGHT JOIN clause
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'RIGHT',
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
        if (is_string($columns)) {
            $columns = [$columns];
        }

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
     * Build SELECT query
     */
    private function buildSelectQuery(): string
    {
        $sql = "SELECT " . implode(', ', $this->select) . " FROM {$this->table}";

        // Add JOINs
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // Add WHERE
        if (!empty($this->where)) {
            $whereClause = $this->buildWhereClause();
            $sql .= $whereClause['sql'];
            $this->bindings = array_merge($this->bindings, $whereClause['bindings']);
        }

        // Add GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        // Add HAVING
        if (!empty($this->having)) {
            $havingClauses = [];
            foreach ($this->having as $having) {
                $havingClauses[] = "{$having['column']} {$having['operator']} ?";
                $this->bindings[] = $having['value'];
            }
            $sql .= " HAVING " . implode(' AND ', $havingClauses);
        }

        // Add ORDER BY
        if (!empty($this->orderBy)) {
            $orderClauses = [];
            foreach ($this->orderBy as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
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
     * Build WHERE clause
     */
    private function buildWhereClause(): array
    {
        $clauses = [];
        $bindings = [];

        foreach ($this->where as $index => $condition) {
            $boolean = $index === 0 ? '' : " {$condition['boolean']} ";

            switch ($condition['type']) {
                case 'where':
                    $clauses[] = $boolean . "`{$condition['column']}` {$condition['operator']} ?";
                    $bindings[] = $condition['value'];
                    break;

                case 'whereIn':
                    $placeholders = implode(', ', array_fill(0, count($condition['values']), '?'));
                    $clauses[] = $boolean . "`{$condition['column']}` IN ({$placeholders})";
                    $bindings = array_merge($bindings, $condition['values']);
                    break;

                case 'whereNotIn':
                    $placeholders = implode(', ', array_fill(0, count($condition['values']), '?'));
                    $clauses[] = $boolean . "`{$condition['column']}` NOT IN ({$placeholders})";
                    $bindings = array_merge($bindings, $condition['values']);
                    break;

                case 'whereNull':
                    $clauses[] = $boolean . "`{$condition['column']}` IS NULL";
                    break;

                case 'whereNotNull':
                    $clauses[] = $boolean . "`{$condition['column']}` IS NOT NULL";
                    break;

                case 'nested':
                    $nestedClause = $condition['query']->buildWhereClause();
                    if (!empty($nestedClause['sql'])) {
                        $clauses[] = $boolean . '(' . ltrim($nestedClause['sql'], ' WHERE') . ')';
                        $bindings = array_merge($bindings, $nestedClause['bindings']);
                    }
                    break;
            }
        }

        $sql = empty($clauses) ? '' : ' WHERE ' . implode('', $clauses);

        return ['sql' => $sql, 'bindings' => $bindings];
    }

    /**
     * Add WHERE condition
     */
    public function where(string|callable $column, string $operator = null, mixed $value = null): self
    {
        // Handle callable for nested conditions
        if (is_callable($column)) {
            $nestedQuery = new static($this->connection, $this->table);
            $column($nestedQuery);

            if (!empty($nestedQuery->where)) {
                $this->where[] = [
                    'type' => 'nested',
                    'query' => $nestedQuery,
                    'boolean' => 'AND'
                ];
            }

            return $this;
        }

        // Handle two-parameter syntax: where('column', 'value')
        if ($operator !== null && $value === null) {
            $value = $operator;
            $operator = '=';
        }

        // Handle three-parameter syntax: where('column', '>', 'value')
        if ($value === null) {
            throw new InvalidArgumentException('WHERE clause requires a value');
        }

        $this->where[] = [
            'type' => 'where',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        return $this;
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
     * Insert record
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

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
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = array_fill(0, count($data), $placeholders);

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