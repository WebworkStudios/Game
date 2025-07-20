<?php

declare(strict_types=1);

namespace Framework\Database;

/**
 * MySQL Grammar - Generiert MySQL-spezifische SQL-Statements
 *
 * REFACTORED: Verwendet MySQLIdentifier für einheitliches Wrapping
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
            $sql .= ' FROM ' . MySQLIdentifier::wrapTable($components['from']);
        }

        if (isset($components['joins'])) {
            $sql .= $this->compileJoins($components['joins']);
        }

        if (isset($components['wheres'])) {
            $sql .= $this->compileWheres($components['wheres']);
        }

        if (isset($components['groups']) && !empty($components['groups'])) {
            $sql .= ' GROUP BY ' . implode(', ', MySQLIdentifier::wrapColumns($components['groups']));
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

        return implode(', ', MySQLIdentifier::wrapColumns($columns));
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
                MySQLIdentifier::wrapTable($join['table']),
                MySQLIdentifier::wrapColumn($join['first']),
                $join['operator'],
                MySQLIdentifier::wrapColumn($join['second'])
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
                    MySQLIdentifier::wrapColumn($where['column']),
                    $where['operator'],
                    $where['binding']
                ),
                'in' => sprintf(
                    '%s IN (%s)',
                    MySQLIdentifier::wrapColumn($where['column']),
                    implode(', ', array_map(fn($b) => ":{$b}", $where['bindings']))
                ),
                'null' => sprintf(
                    '%s IS %sNULL',
                    MySQLIdentifier::wrapColumn($where['column']),
                    $where['not'] ? 'NOT ' : ''
                ),
                'exists' => sprintf(
                    '%sEXISTS (%s)',
                    $where['not'] ? 'NOT ' : '',
                    $where['query']
                ),
                'between' => sprintf(
                    '%s %sBETWEEN :%s AND :%s',
                    MySQLIdentifier::wrapColumn($where['column']),
                    $where['not'] ? 'NOT ' : '',
                    $where['binding_min'],
                    $where['binding_max']
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
                MySQLIdentifier::wrapColumn($having['column']),
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
            $conditions[] = MySQLIdentifier::wrapColumn($order['column']) . ' ' . $order['direction']->value;
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
        $table = MySQLIdentifier::wrapTable($table);

        // Multiple rows
        if (isset($values[0]) && is_array($values[0])) {
            $columns = array_keys($values[0]);
            $wrappedColumns = implode(', ', MySQLIdentifier::wrapColumns($columns));

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
        $wrappedColumns = implode(', ', MySQLIdentifier::wrapColumns($columns));
        $placeholders = ':' . implode(', :', $columns);

        return "INSERT INTO {$table} ({$wrappedColumns}) VALUES ({$placeholders})";
    }

    /**
     * Kompiliert UPDATE Statement
     */
    public function compileUpdate(string $table, array $values, array $wheres): string
    {
        $table = MySQLIdentifier::wrapTable($table);

        $sets = [];
        foreach (array_keys($values) as $column) {
            $sets[] = MySQLIdentifier::wrapColumn($column) . " = :{$column}";
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
        $table = MySQLIdentifier::wrapTable($table);
        $sql = "DELETE FROM {$table}";

        if (!empty($wheres)) {
            $sql .= $this->compileWheres($wheres);
        }

        return $sql;
    }

    /**
     * Kompiliert CREATE TABLE Statement
     */
    public function compileCreateTable(string $table, array $columns, array $options = []): string
    {
        $table = MySQLIdentifier::wrapTable($table);
        $columnDefinitions = [];

        foreach ($columns as $column) {
            $columnDefinitions[] = $this->compileColumnDefinition($column);
        }

        $sql = "CREATE TABLE {$table} (\n    " . implode(",\n    ", $columnDefinitions) . "\n)";

        // Table options
        if (!empty($options)) {
            $sql .= ' ' . implode(' ', $options);
        }

        return $sql;
    }

    /**
     * Kompiliert Column Definition für CREATE TABLE
     */
    private function compileColumnDefinition(array $column): string
    {
        $name = MySQLIdentifier::wrapColumn($column['name']);
        $type = $column['type'];

        $definition = "{$name} {$type}";

        if (isset($column['nullable']) && !$column['nullable']) {
            $definition .= ' NOT NULL';
        }

        if (isset($column['default'])) {
            $definition .= ' DEFAULT ' . $column['default'];
        }

        if (isset($column['auto_increment']) && $column['auto_increment']) {
            $definition .= ' AUTO_INCREMENT';
        }

        if (isset($column['primary']) && $column['primary']) {
            $definition .= ' PRIMARY KEY';
        }

        return $definition;
    }
}