<?php
declare(strict_types=1);

namespace Framework\Validation\Rules;

use Framework\Database\ConnectionManager;
use Framework\Database\MySQLGrammar;
use Framework\Database\QueryBuilder;
use InvalidArgumentException;

/**
 * ExistsRule - Field value must exist in MySQL database table
 *
 * Usage: exists:table,column,where_column,where_value,connection
 * Examples:
 * - exists:users,id
 * - exists:users,email
 * - exists:posts,id,status,published
 * - exists:game_players,id,,,game (different connection)
 */
class ExistsRule implements RuleInterface
{
    public function __construct(
        private readonly ?ConnectionManager $connectionManager = null
    )
    {
        if ($this->connectionManager === null) {
            throw new InvalidArgumentException('ExistsRule requires ConnectionManager');
        }
    }

    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let required rule handle empty values
        }

        if (count($parameters) < 2) {
            throw new InvalidArgumentException('Exists rule requires at least table and column parameters');
        }

        $table = $parameters[0];
        $column = $parameters[1];
        $whereColumn = $parameters[2] ?? null;
        $whereValue = $parameters[3] ?? null;
        $connection = $parameters[4] ?? 'default';

        return $this->exists($table, $column, $value, $whereColumn, $whereValue, $connection);
    }

    /**
     * Check if value exists in MySQL database using QueryBuilder
     */
    private function exists(string $table, string $column, mixed $value, ?string $whereColumn, ?string $whereValue, string $connection): bool
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

            // Add additional where condition
            if ($whereColumn !== null && $whereValue !== null) {
                $query->where($whereColumn, $whereValue);
            }

            $count = $query->count();

            return $count > 0;

        } catch (\Exception $e) {
            // Log error in production, throw in development
            error_log("ExistsRule MySQL validation error: " . $e->getMessage());
            throw new \RuntimeException("MySQL validation failed: " . $e->getMessage());
        }
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The selected {$field} is invalid.";
    }
}
