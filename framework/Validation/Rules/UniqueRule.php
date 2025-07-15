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
     * Check if value is unique in MySQL database using QueryBuilder
     */
    private function isUnique(string $table, string $column, mixed $value, ?string $ignoreId, string $idColumn, string $connection): bool
    {
        try {
            // Create QueryBuilder with MySQL Grammar
            $query = new QueryBuilder(
                connectionManager: $this->connectionManager,
                grammar: new MySQLGrammar(),
                connectionName: $connection
            );

            $query = $query
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
            error_log("UniqueRule MySQL validation error: " . $e->getMessage());
            throw new \RuntimeException("MySQL validation failed: " . $e->getMessage());
        }
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} has already been taken.";
    }
}
