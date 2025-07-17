<?php
declare(strict_types=1);

namespace Framework\Validation\Rules;

use Framework\Database\ConnectionManager;
use Framework\Database\MySQLGrammar;
use Framework\Database\QueryBuilder;
use InvalidArgumentException;

/**
 * UniqueRule - Field value must be unique in MySQL database table
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
    ) {
        if ($this->connectionManager === null) {
            throw new InvalidArgumentException('UniqueRule requires ConnectionManager');
        }
    }

    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (count($parameters) < 2) {
            throw new InvalidArgumentException('Unique rule requires at least table and column parameters');
        }

        [$table, $column, $ignoreId, $idColumn, $connection] = [
            $parameters[0],
            $parameters[1],
            $parameters[2] ?? null,
            $parameters[3] ?? 'id',
            $parameters[4] ?? 'default'
        ];

        return $this->isUnique($table, $column, $value, $ignoreId, $idColumn, $connection);
    }

    private function isUnique(string $table, string $column, mixed $value, ?string $ignoreId, string $idColumn, string $connection): bool
    {
        try {
            $query = new QueryBuilder(
                connectionManager: $this->connectionManager,
                grammar: new MySQLGrammar(),
                connectionName: $connection
            );

            $query = $query
                ->table($table)
                ->where($column, $value);

            if ($ignoreId !== null) {
                $query->where($idColumn, '!=', $ignoreId);
            }

            return $query->count() === 0;

        } catch (\Exception $e) {
            error_log("UniqueRule validation error: " . $e->getMessage());
            throw new \RuntimeException("Database validation failed: " . $e->getMessage());
        }
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} has already been taken.";
    }
}