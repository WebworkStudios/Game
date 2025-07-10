<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

use Framework\Database\ConnectionManager;
use InvalidArgumentException;

/**
 * UniqueRule - Field value must be unique in database table
 *
 * Usage: unique:table,column,ignore_id,id_column,connection
 * Examples:
 * - unique:users,email
 * - unique:users,email,5
 * - unique:users,email,5,user_id
 * - unique:forum_posts,title,,,forum (different connection)
 */
class UniqueRule implements RuleInterface
{
    public function __construct(
        private readonly ?ConnectionManager $connectionManager = null
    )
    {
        if ($this->connectionManager === null) {
            throw new InvalidArgumentException('UniqueRule requires ConnectionManager');
        }
    }

    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let required rule handle empty values
        }

        if (count($parameters) < 2) {
            throw new InvalidArgumentException('Unique rule requires at least table and column parameters');
        }

        $table = $parameters[0];
        $column = $parameters[1];
        $ignoreId = $parameters[2] ?? null;
        $idColumn = $parameters[3] ?? 'id';
        $connection = $parameters[4] ?? 'default';

        return $this->isUnique($table, $column, $value, $ignoreId, $idColumn, $connection);
    }

    /**
     * Check if value is unique in database using QueryBuilder
     */
    private function isUnique(string $table, string $column, mixed $value, ?string $ignoreId, string $idColumn, string $connection): bool
    {
        try {
            // Create QueryBuilder factory for the specific connection
            $queryFactory = function (string $connectionName) {
                return new \Framework\Database\QueryBuilder(
                    connectionManager: $this->connectionManager,
                    grammar: new \Framework\Database\SqlGrammar(),
                    connectionName: $connectionName
                );
            };

            $query = $queryFactory($connection)
                ->table($table)
                ->where($column, $value);

            // Add ignore condition for updates
            if ($ignoreId !== null) {
                $query->where($idColumn, '!=', $ignoreId);
            }

            $count = $query->count();

            return $count === 0;

        } catch (\Exception $e) {
            // Log error in production, throw in development
            error_log("UniqueRule database error: " . $e->getMessage());
            throw new \RuntimeException("Database validation failed: " . $e->getMessage());
        }
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} has already been taken.";
    }
}