<?php


declare(strict_types=1);

namespace Framework\Database;

use Framework\Database\Enums\DatabaseDriver;

/**
 * SQL Grammar - Generiert SQL-Statements für verschiedene Datenbanken
 */
class SqlGrammar
{
    private const array MYSQL_KEYWORDS = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'FROM', 'WHERE', 'JOIN',
        'ORDER', 'GROUP', 'HAVING', 'LIMIT', 'OFFSET'
    ];

    public function __construct(
        private readonly DatabaseDriver $driver = DatabaseDriver::MYSQL
    )
    {
    }

    /**
     * Erstellt SELECT Statement
     */
    public function compileSelect(array $components): string
    {
        $sql = 'SELECT ' . $this->compileColumns($components['select'] ?? ['*']);

        if (isset($components['from'])) {
            $sql .= ' FROM ' . $this->wrapTable($components['from']);
        }

        if (isset($components['joins'])) {
            $sql .= $this->compileJoins($components['joins']);
        }

        if (isset($components['wheres'])) {
            $sql .= $this->compileWheres($components['wheres']);
        }

        if (isset($components['groups']) && !empty($components['groups'])) {
            $sql .= ' GROUP BY ' . implode(', ', array_map([$this, 'wrapColumn'], $components['groups']));
        }

        if (isset($components['havings'])) {
            $sql .= $this->compileHavings($components['havings']);
        }

        if (isset($components['orders'])) {
            $sql .= $this->compileOrders($components['orders']);
        }

        if (isset($components['limit'])) {
            $sql .= $this->compileLimit($components['limit'], $components['offset'] ?? null);
        }

        return $sql;
    }

    /**
     * Erstellt INSERT Statement
     */
    public function compileInsert(string $table, array $values): string
    {
        $table = $this->wrapTable($table);

        if (empty($values)) {
            throw new \InvalidArgumentException('Insert values cannot be empty');
        }

        // Single row insert
        if (isset($values[0]) && is_array($values[0])) {
            return $this->compileInsertMultiple($table, $values);
        }

        // Single row insert
        $columns = array_keys($values);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', array_map([$this, 'wrapColumn'], $columns)),
            implode(', ', $placeholders)
        );
    }

    /**
     * Erstellt UPDATE Statement
     */
    public function compileUpdate(string $table, array $values, array $wheres): string
    {
        $table = $this->wrapTable($table);

        $sets = [];
        foreach ($values as $column => $value) {
            $sets[] = $this->wrapColumn($column) . " = :{$column}";
        }

        $sql = sprintf('UPDATE %s SET %s', $table, implode(', ', $sets));

        if (!empty($wheres)) {
            $sql .= $this->compileWheres($wheres);
        }

        return $sql;
    }

    /**
     * Erstellt DELETE Statement
     */
    public function compileDelete(string $table, array $wheres): string
    {
        $sql = 'DELETE FROM ' . $this->wrapTable($table);

        if (!empty($wheres)) {
            $sql .= $this->compileWheres($wheres);
        }

        return $sql;
    }

    /**
     * Kompiliert Spalten
     */
    private function compileColumns(array $columns): string
    {
        if (in_array('*', $columns)) {
            return '*';
        }

        return implode(', ', array_map([$this, 'wrapColumn'], $columns));
    }

    /**
     * Kompiliert JOINs
     */
    private function compileJoins(array $joins): string
    {
        $sql = '';

        foreach ($joins as $join) {
            $sql .= sprintf(
                ' %s %s ON %s %s %s',
                $join['type']->value,
                $this->wrapTable($join['table']),
                $this->wrapColumn($join['first']),
                $join['operator'],
                $this->wrapColumn($join['second'])
            );
        }

        return $sql;
    }

    /**
     * Kompiliert WHERE Clauses
     */
    private function compileWheres(array $wheres): string
    {
        if (empty($wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        $conditions = [];

        foreach ($wheres as $where) {
            $condition = match ($where['type']) {
                'basic' => $this->compileBasicWhere($where),
                'in' => $this->compileInWhere($where),
                'null' => $this->compileNullWhere($where),
                'between' => $this->compileBetweenWhere($where),
                'raw' => $where['sql'],
                default => throw new \InvalidArgumentException("Unknown where type: {$where['type']}")
            };

            $conditions[] = $condition;
        }

        return $sql . implode(' AND ', $conditions);
    }

    /**
     * Kompiliert HAVING Clauses
     */
    private function compileHavings(array $havings): string
    {
        if (empty($havings)) {
            return '';
        }

        $sql = ' HAVING ';
        $conditions = [];

        foreach ($havings as $having) {
            $conditions[] = sprintf(
                '%s %s :%s',
                $this->wrapColumn($having['column']),
                $having['operator'],
                $having['binding']
            );
        }

        return $sql . implode(' AND ', $conditions);
    }

    /**
     * Kompiliert ORDER BY
     */
    private function compileOrders(array $orders): string
    {
        if (empty($orders)) {
            return '';
        }

        $orderClauses = [];
        foreach ($orders as $order) {
            $orderClauses[] = $this->wrapColumn($order['column']) . ' ' . $order['direction']->value;
        }

        return ' ORDER BY ' . implode(', ', $orderClauses);
    }

    /**
     * Kompiliert LIMIT/OFFSET
     */
    private function compileLimit(int $limit, ?int $offset = null): string
    {
        return match ($this->driver) {
            DatabaseDriver::MYSQL, DatabaseDriver::SQLITE => $this->compileMysqlLimit($limit, $offset),
            DatabaseDriver::POSTGRESQL => $this->compilePostgresLimit($limit, $offset),
        };
    }

    /**
     * MySQL/SQLite LIMIT Syntax
     */
    private function compileMysqlLimit(int $limit, ?int $offset = null): string
    {
        $sql = " LIMIT {$limit}";

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        return $sql;
    }

    /**
     * PostgreSQL LIMIT Syntax
     */
    private function compilePostgresLimit(int $limit, ?int $offset = null): string
    {
        $sql = " LIMIT {$limit}";

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        return $sql;
    }

    /**
     * Multiple Row Insert
     */
    private function compileInsertMultiple(string $table, array $rows): string
    {
        $columns = array_keys($rows[0]);
        $placeholderRows = [];

        foreach ($rows as $index => $row) {
            $placeholders = [];
            foreach ($columns as $column) {
                $placeholders[] = ":{$column}_{$index}";
            }
            $placeholderRows[] = '(' . implode(', ', $placeholders) . ')';
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $table,
            implode(', ', array_map([$this, 'wrapColumn'], $columns)),
            implode(', ', $placeholderRows)
        );
    }

    /**
     * Basic WHERE Condition
     */
    private function compileBasicWhere(array $where): string
    {
        return sprintf(
            '%s %s :%s',
            $this->wrapColumn($where['column']),
            $where['operator'],
            $where['binding']
        );
    }

    /**
     * IN WHERE Condition
     */
    private function compileInWhere(array $where): string
    {
        $placeholders = array_map(fn($i) => ":{$where['binding']}_{$i}", array_keys($where['values']));

        return sprintf(
            '%s %s (%s)',
            $this->wrapColumn($where['column']),
            $where['not'] ? 'NOT IN' : 'IN',
            implode(', ', $placeholders)
        );
    }

    /**
     * NULL WHERE Condition
     */
    private function compileNullWhere(array $where): string
    {
        return sprintf(
            '%s %s',
            $this->wrapColumn($where['column']),
            $where['not'] ? 'IS NOT NULL' : 'IS NULL'
        );
    }

    /**
     * BETWEEN WHERE Condition
     */
    private function compileBetweenWhere(array $where): string
    {
        return sprintf(
            '%s %s :%s_min AND :%s_max',
            $this->wrapColumn($where['column']),
            $where['not'] ? 'NOT BETWEEN' : 'BETWEEN',
            $where['binding'],
            $where['binding']
        );
    }

    /**
     * Wraps table name mit Driver-spezifischen Quotes
     */
    public function wrapTable(string $table): string
    {
        return match ($this->driver) {
            DatabaseDriver::MYSQL => "`{$table}`",
            DatabaseDriver::POSTGRESQL => "\"{$table}\"",
            DatabaseDriver::SQLITE => "`{$table}`",
        };
    }

    /**
     * Wraps column name mit Driver-spezifischen Quotes
     */
    public function wrapColumn(string $column): string
    {
        // Handle table.column notation
        if (str_contains($column, '.')) {
            [$table, $col] = explode('.', $column, 2);
            return $this->wrapTable($table) . '.' . $this->wrapColumn($col);
        }

        // Skip if already wrapped or is a function
        if (str_contains($column, '(') || str_contains($column, '`') || str_contains($column, '"')) {
            return $column;
        }

        return match ($this->driver) {
            DatabaseDriver::MYSQL => "`{$column}`",
            DatabaseDriver::POSTGRESQL => "\"{$column}\"",
            DatabaseDriver::SQLITE => "`{$column}`",
        };
    }

    /**
     * Escaped Parameter Name für Bindings
     */
    public function parameter(string $name): string
    {
        return ':' . preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    }
}