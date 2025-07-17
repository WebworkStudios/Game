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
 * MODERNISIERUNGEN PHP 8.4:
 * ✅ Readonly constructor property promotion
 * ✅ Named arguments bei QueryBuilder
 * ✅ Match expression für parameter validation
 * ✅ Modern exception handling mit context
 * ✅ Improved error logging
 * ✅ Type-safe parameter handling
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
    ) {
        if ($this->connectionManager === null) {
            throw new InvalidArgumentException('ExistsRule requires ConnectionManager dependency injection');
        }
    }

    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        // Null/empty values sind erlaubt - required rule für non-empty
        if ($value === null || $value === '') {
            return true;
        }

        // Parameter-Validierung mit modernem approach
        $this->validateParameters($parameters);

        // Parameter extrahieren mit array destructuring
        [$table, $column, $whereColumn, $whereValue, $connection] = $this->extractParameters($parameters);

        return $this->checkExists($table, $column, $value, $whereColumn, $whereValue, $connection);
    }

    /**
     * Parameter-Validierung mit match expression
     */
    private function validateParameters(array $parameters): void
    {
        $paramCount = count($parameters);

        match (true) {
            $paramCount < 2 => throw new InvalidArgumentException(
                'ExistsRule requires at least table and column parameters. ' .
                'Usage: exists:table,column[,where_column,where_value,connection]'
            ),
            $paramCount > 5 => throw new InvalidArgumentException(
                'ExistsRule accepts maximum 5 parameters: table,column,where_column,where_value,connection'
            ),
            default => null // Valid parameter count
        };
    }

    /**
     * Parameter extrahieren mit Default-Values
     *
     * @return array{string, string, ?string, ?string, string}
     */
    private function extractParameters(array $parameters): array
    {
        return [
            $parameters[0],                    // table (required)
            $parameters[1],                    // column (required)
            $parameters[2] ?? null,            // whereColumn (optional)
            $parameters[3] ?? null,            // whereValue (optional)
            $parameters[4] ?? 'default'        // connection (optional, default: 'default')
        ];
    }

    /**
     * Database-Existenz prüfen mit modernem QueryBuilder
     */
    private function checkExists(
        string $table,
        string $column,
        mixed $value,
        ?string $whereColumn,
        ?string $whereValue,
        string $connection
    ): bool {
        try {
            // QueryBuilder mit named arguments (PHP 8.0+)
            $query = new QueryBuilder(
                connectionManager: $this->connectionManager,
                grammar: new MySQLGrammar(),
                connectionName: $connection
            );

            // Base query aufbauen
            $query = $query
                ->table($table)
                ->where($column, $value);

            // Optional: Additional where condition
            if ($whereColumn !== null && $whereValue !== null) {
                $query = $query->where($whereColumn, $whereValue);
            }

            // Existenz prüfen
            $count = $query->count();

            return $count > 0;

        } catch (\PDOException $e) {
            // Database-spezifische Errors
            $this->logDatabaseError($e, $table, $column, $value, $connection);
            throw new \RuntimeException(
                "Database connection failed for exists validation on {$table}.{$column}",
                previous: $e
            );

        } catch (\Exception $e) {
            // Allgemeine Errors
            $this->logValidationError($e, $table, $column, $value);
            throw new \RuntimeException(
                "ExistsRule validation failed for {$table}.{$column}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Database-Error Logging mit Context
     */
    private function logDatabaseError(\PDOException $e, string $table, string $column, mixed $value, string $connection): void
    {
        error_log(sprintf(
            "ExistsRule PDO Error: %s | Table: %s | Column: %s | Value: %s | Connection: %s",
            $e->getMessage(),
            $table,
            $column,
            $this->sanitizeValueForLog($value),
            $connection
        ));
    }

    /**
     * Allgemeines Error Logging
     */
    private function logValidationError(\Exception $e, string $table, string $column, mixed $value): void
    {
        error_log(sprintf(
            "ExistsRule Validation Error: %s | Context: %s.%s = %s",
            $e->getMessage(),
            $table,
            $column,
            $this->sanitizeValueForLog($value)
        ));
    }

    /**
     * Value für Logging sanitizen (Security)
     */
    private function sanitizeValueForLog(mixed $value): string
    {
        return match (true) {
            is_string($value) => mb_strlen($value) > 100 ? mb_substr($value, 0, 100) . '...' : $value,
            is_numeric($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'NULL',
            default => '[' . gettype($value) . ']'
        };
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The selected {$field} is invalid.";
    }
}