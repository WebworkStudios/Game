<?php
declare(strict_types=1);

namespace Framework\Database;

/**
 * MySQL Grammar - Generiert MySQL-spezifische SQL-Statements
 */
class MySQLGrammar
{
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
     * Wraps table name mit MySQL Backticks
     */
    public function wrapTable(string $table): string
    {
        return "`{$table}`";
    }

    /**
     * Wraps column name mit MySQL Backticks
     */
    public function wrapColumn(string $column): string
    {
        // Handle table.column notation
        if (str_contains($column, '.')) {
            [$table, $col] = explode('.', $column, 2);
            return $this->wrapTable($table) . '.' . $this->wrapColumn($col);
        }

        // Don't wrap functions or *
        if (str_contains($column, '(') || $column === '*') {
            return $column;
        }

        return "`{$column}`";
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
     * Kompiliert WHERE Conditions
     */
    private function compileWheres(array $wheres): string
    {
        if (empty($wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        $conditions = [];

        foreach ($wheres as $where) {
            $conditions[] = match ($where['type']) {
                'basic' => sprintf(
                    '%s %s :%s',
                    $this->wrapColumn($where['column']),
                    $where['operator'],
                    $where['binding']
                ),
                'in' => sprintf(
                    '%s IN (%s)',
                    $this->wrapColumn($where['column']),
                    implode(', ', array_map(fn($b) => ":{$b}", $where['bindings']))
                ),
                'null' => sprintf(
                    '%s IS %sNULL',
                    $this->wrapColumn($where['column']),
                    $where['not'] ? 'NOT ' : ''
                ),
                default => throw new \InvalidArgumentException("Unsupported where type: {$where['type']}")
            };
        }

        return $sql . implode(' AND ', $conditions);
    }

    /**
     * Kompiliert HAVING Conditions
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

        $conditions = [];
        foreach ($orders as $order) {
            $conditions[] = $this->wrapColumn($order['column']) . ' ' . $order['direction']->value;
        }

        return ' ORDER BY ' . implode(', ', $conditions);
    }

    /**
     * Kompiliert LIMIT/OFFSET
     */
    private function compileLimit(int $limit, ?int $offset = null): string
    {
        $sql = " LIMIT {$limit}";

        if ($offset !== null && $offset > 0) {
            $sql .= " OFFSET {$offset}";
        }

        return $sql;
    }

    /**
     * Kompiliert INSERT Statement
     */
    public function compileInsert(string $table, array $values): string
    {
        $table = $this->wrapTable($table);

        // Multiple rows
        if (isset($values[0]) && is_array($values[0])) {
            $columns = array_keys($values[0]);
            $wrappedColumns = implode(', ', array_map([$this, 'wrapColumn'], $columns));

            $valueGroups = [];
            foreach ($values as $index => $row) {
                $placeholders = [];
                foreach ($columns as $column) {
                    $placeholders[] = ":{$column}_{$index}";
                }
                $valueGroups[] = '(' . implode(', ', $placeholders) . ')';
            }

            return "INSERT INTO {$table} ({$wrappedColumns}) VALUES " . implode(', ', $valueGroups);
        }

        // Single row
        $columns = array_keys($values);
        $wrappedColumns = implode(', ', array_map([$this, 'wrapColumn'], $columns));
        $placeholders = ':' . implode(', :', $columns);

        return "INSERT INTO {$table} ({$wrappedColumns}) VALUES ({$placeholders})";
    }

    /**
     * Kompiliert UPDATE Statement
     */
    public function compileUpdate(string $table, array $values, array $wheres): string
    {
        $table = $this->wrapTable($table);

        $sets = [];
        foreach (array_keys($values) as $column) {
            $sets[] = $this->wrapColumn($column) . " = :{$column}";
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets);

        if (!empty($wheres)) {
            $sql .= $this->compileWheres($wheres);
        }

        return $sql;
    }

    /**
     * Kompiliert DELETE Statement
     */
    public function compileDelete(string $table, array $wheres): string
    {
        $table = $this->wrapTable($table);
        $sql = "DELETE FROM {$table}";

        if (!empty($wheres)) {
            $sql .= $this->compileWheres($wheres);
        }

        return $sql;
    }
}