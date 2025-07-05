<?php
/**
 * Database Connection Pool - Complete Fixed Version
 * Manages multiple database connections with read/write separation
 *
 * File: framework/Database/ConnectionPool.php
 * Directory: /framework/Database/
 */

declare(strict_types=1);

namespace Framework\Database;

use InvalidArgumentException;
use PDO;
use PDOException;

class ConnectionPool
{
    /** @var array<string, PDO> */
    private array $writeConnections = [];

    /** @var array<string, array<PDO>> */
    private array $readConnections = [];

    /** @var array */
    private array $config;

    /** @var array<string, int> */
    private array $readConnectionIndexes = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        // Connections are created lazily when first accessed
        // This prevents connection errors during bootstrap
    }

    /**
     * Get query builder for table (read operations)
     */
    public function table(string $table, string $connection = 'mysql'): QueryBuilder
    {
        return new QueryBuilder($this->getReadConnection($connection), $table);
    }

    /**
     * Get query builder for write operations
     */
    public function writeTable(string $table, string $connection = 'mysql'): QueryBuilder
    {
        return new QueryBuilder($this->getWriteConnection($connection), $table);
    }

    /**
     * Get read connection (load balanced)
     */
    public function getReadConnection(string $name = 'mysql'): PDO
    {
        if (!isset($this->config['connections'][$name])) {
            throw new InvalidArgumentException("Connection configuration [{$name}] not found");
        }

        $connectionConfig = $this->config['connections'][$name];

        // Initialize read connections lazily
        if (!isset($this->readConnections[$name])) {
            $this->readConnections[$name] = [];
            $this->readConnectionIndexes[$name] = 0;

            if (isset($connectionConfig['read']) && !empty($connectionConfig['read'])) {
                foreach ($connectionConfig['read'] as $readConfig) {
                    try {
                        $this->readConnections[$name][] = $this->createConnection($readConfig);
                    } catch (PDOException $e) {
                        // Log read connection failure but continue with write connection
                        error_log("Read connection failed: " . $e->getMessage());
                    }
                }
            }

            // If no read connections available, use write connection
            if (empty($this->readConnections[$name])) {
                $this->readConnections[$name][] = $this->getWriteConnection($name);
            }
        }

        $connections = $this->readConnections[$name];

        if (empty($connections)) {
            return $this->getWriteConnection($name);
        }

        // Round-robin load balancing
        $index = $this->readConnectionIndexes[$name];
        $connection = $connections[$index];

        $this->readConnectionIndexes[$name] = ($index + 1) % count($connections);

        return $connection;
    }

    /**
     * Get write connection
     */
    public function getWriteConnection(string $name = 'mysql'): PDO
    {
        if (!isset($this->config['connections'][$name])) {
            throw new InvalidArgumentException("Connection configuration [{$name}] not found");
        }

        // Create connection lazily
        if (!isset($this->writeConnections[$name])) {
            $this->writeConnections[$name] = $this->createConnection(
                $this->config['connections'][$name]['write']
            );
        }

        return $this->writeConnections[$name];
    }

    /**
     * Create a PDO connection
     */
    private function createConnection(array $config): PDO
    {
        $dsn = $this->buildDsn($config);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE {$config['collation']}",
            PDO::ATTR_TIMEOUT => $config['timeout'] ?? 30,
            PDO::ATTR_PERSISTENT => $config['persistent'] ?? false,
        ];

        // Add SSL options if configured
        if (!empty($config['ssl']) && is_array($config['ssl'])) {
            if (!empty($config['ssl']['ca'])) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $config['ssl']['ca'];
            }
            if (!empty($config['ssl']['cert'])) {
                $options[PDO::MYSQL_ATTR_SSL_CERT] = $config['ssl']['cert'];
            }
            if (!empty($config['ssl']['key'])) {
                $options[PDO::MYSQL_ATTR_SSL_KEY] = $config['ssl']['key'];
            }
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $config['ssl']['verify_server_cert'] ?? false;
        }

        try {
            return new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $options
            );
        } catch (PDOException $e) {
            throw new PDOException(
                "Database connection [{$config['host']}:{$config['port']}] failed: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Build DSN string
     */
    private function buildDsn(array $config): string
    {
        $driver = $config['driver'] ?? 'mysql';

        switch ($driver) {
            case 'mysql':
                return sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $config['host'],
                    $config['port'] ?? 3306,
                    $config['database'],
                    $config['charset'] ?? 'utf8mb4'
                );

            case 'pgsql':
                return sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    $config['host'],
                    $config['port'] ?? 5432,
                    $config['database']
                );

            case 'sqlite':
                return 'sqlite:' . $config['database'];

            default:
                throw new InvalidArgumentException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * Execute a transaction
     */
    public function transaction(callable $callback, string $connection = 'mysql'): mixed
    {
        $pdo = $this->getWriteConnection($connection);

        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Test all connections
     */
    public function testConnections(): array
    {
        $results = [];

        foreach ($this->config['connections'] as $name => $connectionConfig) {
            $results[$name] = [
                'write' => false,
                'read' => []
            ];

            // Test write connection
            try {
                $writeConnection = $this->getWriteConnection($name);
                $results[$name]['write'] = $this->testConnection($writeConnection);
            } catch (\Exception $e) {
                $results[$name]['write'] = false;
                $results[$name]['write_error'] = $e->getMessage();
            }

            // Test read connections
            try {
                if (isset($connectionConfig['read']) && !empty($connectionConfig['read'])) {
                    foreach ($connectionConfig['read'] as $index => $readConfig) {
                        try {
                            $readConnection = $this->createConnection($readConfig);
                            $results[$name]['read'][$index] = $this->testConnection($readConnection);
                        } catch (\Exception $e) {
                            $results[$name]['read'][$index] = false;
                            $results[$name]['read_error_' . $index] = $e->getMessage();
                        }
                    }
                } else {
                    // Use write connection result for read
                    $results[$name]['read'][0] = $results[$name]['write'];
                }
            } catch (\Exception $e) {
                $results[$name]['read'][0] = false;
                $results[$name]['read_error'] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Test a single connection
     */
    private function testConnection(PDO $connection): bool
    {
        try {
            $connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Close all connections
     */
    public function closeConnections(): void
    {
        $this->writeConnections = [];
        $this->readConnections = [];
        $this->readConnectionIndexes = [];
    }

    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        $stats = [];

        foreach ($this->config['connections'] as $name => $connectionConfig) {
            $stats[$name] = [
                'write_connections' => isset($this->writeConnections[$name]) ? 1 : 0,
                'read_connections' => count($this->readConnections[$name] ?? []),
                'current_read_index' => $this->readConnectionIndexes[$name] ?? 0,
                'write_active' => isset($this->writeConnections[$name]),
                'read_active' => !empty($this->readConnections[$name])
            ];
        }

        return $stats;
    }

    /**
     * Get available connection names
     */
    public function getConnectionNames(): array
    {
        return array_keys($this->config['connections']);
    }

    /**
     * Check if connection is configured
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->config['connections'][$name]);
    }

    /**
     * Get connection configuration
     */
    public function getConnectionConfig(string $name): array
    {
        if (!$this->hasConnection($name)) {
            throw new InvalidArgumentException("Connection configuration [{$name}] not found");
        }

        return $this->config['connections'][$name];
    }

    /**
     * Force reconnection for a specific connection
     */
    public function reconnect(string $name = 'mysql'): void
    {
        if (isset($this->writeConnections[$name])) {
            unset($this->writeConnections[$name]);
        }

        if (isset($this->readConnections[$name])) {
            unset($this->readConnections[$name]);
            unset($this->readConnectionIndexes[$name]);
        }
    }

    /**
     * Execute raw SQL query on write connection
     */
    public function statement(string $query, array $bindings = [], string $connection = 'mysql'): bool
    {
        $pdo = $this->getWriteConnection($connection);
        $stmt = $pdo->prepare($query);
        return $stmt->execute($bindings);
    }

    /**
     * Execute raw SQL query and return results
     */
    public function select(string $query, array $bindings = [], string $connection = 'mysql'): array
    {
        $pdo = $this->getReadConnection($connection);
        $stmt = $pdo->prepare($query);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute INSERT and return last insert ID
     */
    public function insert(string $query, array $bindings = [], string $connection = 'mysql'): int
    {
        $pdo = $this->getWriteConnection($connection);
        $stmt = $pdo->prepare($query);
        $stmt->execute($bindings);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Execute UPDATE/DELETE and return affected rows
     */
    public function affectingStatement(string $query, array $bindings = [], string $connection = 'mysql'): int
    {
        $pdo = $this->getWriteConnection($connection);
        $stmt = $pdo->prepare($query);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /**
     * Get the default connection name
     */
    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'mysql';
    }

    /**
     * Set default connection
     */
    public function setDefaultConnection(string $name): void
    {
        if (!$this->hasConnection($name)) {
            throw new InvalidArgumentException("Connection [{$name}] is not configured");
        }

        $this->config['default'] = $name;
    }
}