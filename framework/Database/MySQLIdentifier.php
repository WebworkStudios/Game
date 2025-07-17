<?php

declare(strict_types=1);

namespace Framework\Database;

/**
 * MySQL Identifier Utility - Zentralisierte SQL-Wrapping-Logik
 *
 * Eliminiert Code-Duplikation bei Table/Column-Wrapping
 */
class MySQLIdentifier
{
    /**
     * Wraps MySQL table name mit Backticks
     */
    public static function wrapTable(string $table): string
    {
        // Bereits gewrappte Identifier nicht nochmal wrappen
        if (str_starts_with($table, '`') && str_ends_with($table, '`')) {
            return $table;
        }

        // Alias-Handling: "users u" -> "`users` u"
        if (str_contains($table, ' ')) {
            $parts = explode(' ', $table, 2);
            return self::wrapTable($parts[0]) . ' ' . $parts[1];
        }

        return "`{$table}`";
    }

    /**
     * Wraps MySQL column name mit Backticks
     */
    public static function wrapColumn(string $column): string
    {
        // Bereits gewrappte Identifier nicht nochmal wrappen
        if (str_starts_with($column, '`') && str_ends_with($column, '`')) {
            return $column;
        }

        // Spezielle Fälle: *, Funktionen, etc.
        if (self::isSpecialColumn($column)) {
            return $column;
        }

        // Table.column notation: "users.id" -> "`users`.`id`"
        if (str_contains($column, '.')) {
            return self::wrapQualifiedColumn($column);
        }

        // Alias-Handling: "name AS user_name" -> "`name` AS user_name"
        if (self::hasAlias($column)) {
            return self::wrapColumnWithAlias($column);
        }

        return "`{$column}`";
    }

    /**
     * Prüft ob Column ein spezieller Ausdruck ist
     */
    private static function isSpecialColumn(string $column): bool
    {
        // Wildcard
        if ($column === '*') {
            return true;
        }

        // Funktionen: COUNT(*), SUM(amount), etc.
        if (str_contains($column, '(') && str_contains($column, ')')) {
            return true;
        }

        // Numerische Werte
        if (is_numeric($column)) {
            return true;
        }

        // SQL Keywords/Expressions
        $keywords = ['NULL', 'DEFAULT', 'CURRENT_TIMESTAMP', 'NOW()', 'UUID()'];
        return in_array(strtoupper($column), $keywords, true);
    }

    /**
     * Wraps qualified column (table.column)
     */
    private static function wrapQualifiedColumn(string $column): string
    {
        $parts = explode('.', $column);
        $wrappedParts = [];

        foreach ($parts as $part) {
            if (self::isSpecialColumn($part)) {
                $wrappedParts[] = $part;
            } else {
                $wrappedParts[] = "`{$part}`";
            }
        }

        return implode('.', $wrappedParts);
    }

    /**
     * Prüft ob Column einen Alias hat
     */
    private static function hasAlias(string $column): bool
    {
        return stripos($column, ' AS ') !== false;
    }

    /**
     * Wraps column mit Alias
     */
    private static function wrapColumnWithAlias(string $column): string
    {
        $parts = preg_split('/\s+AS\s+/i', $column, 2);

        if (count($parts) === 2) {
            $columnPart = self::wrapColumn($parts[0]);
            $aliasPart = $parts[1]; // Alias nicht wrappen

            return $columnPart . ' AS ' . $aliasPart;
        }

        return self::wrapColumn($column);
    }

    /**
     * Wraps multiple columns in einem Zug
     */
    public static function wrapColumns(array $columns): array
    {
        return array_map([self::class, 'wrapColumn'], $columns);
    }

    /**
     * Wraps multiple tables in einem Zug
     */
    public static function wrapTables(array $tables): array
    {
        return array_map([self::class, 'wrapTable'], $tables);
    }

    /**
     * Erstellt sicheren MySQL-Identifier
     */
    public static function sanitizeIdentifier(string $identifier): string
    {
        // Entferne gefährliche Zeichen
        $cleaned = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);

        // Stelle sicher, dass es nicht leer ist
        if (empty($cleaned)) {
            throw new \InvalidArgumentException("Invalid MySQL identifier: '{$identifier}'");
        }

        return $cleaned;
    }

    /**
     * Prüft ob Identifier gültig ist
     */
    public static function isValidIdentifier(string $identifier): bool
    {
        // MySQL identifier rules
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) === 1;
    }
}