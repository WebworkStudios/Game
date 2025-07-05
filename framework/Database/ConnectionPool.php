<?php
/**
 * Database Connection Pool
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
        $this->initializeConnections();
    }

    /**
     * Initialize database connections
     */
    private function initializeConnections(): void
    {
        foreach ($this->config['connections'] as $name => $connectionConfig) {
            // Initialize connection arrays
            $this->readConnections[$name] = [];
            $this->readConnectionIndexes[$name] = 0;

            // Note: Connections are created lazily when first accessed
            // This prevents connection errors during bootstrap
        }
    }

    /**
     * Get query builder for table
     */
    public function table(string $table, string $connection = 'default'): QueryBuilder
    {
        return new QueryBuilder($this->getReadConnection($connection), $table);
    }

    /**
     * Get read connection (load balanced)
     */
    public function getReadConnection(string $name = 'default'): PDO
    {
        if (!isset($this->config['connections'][$name])) {
            throw new InvalidArgumentException("Connection configuration [{$name}] not found");
        }

        $connectionConfig = $this->config['connections'][$name];

        // Initialize read connections lazily
        if (empty($this->readConnections[$name])) {
            if (isset($connectionConfig['read'])) {
                foreach ($connectionConfig['read'] as $readConfig) {
                    $this->readConnections[$name][] = $this->createConnection($readConfig);
                }
            } else {
                // Use write connection as read connection if no read replicas
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
        if (!empty($config['ssl'])) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $config['ssl']['ca'] ?? null;
            $options[PDO::MYSQL_ATTR_SSL_CERT] = $config['ssl']['cert'] ?? null;
            $options[PDO::MYSQL_ATTR_SSL_KEY] = $config['ssl']['key'] ?? null;
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
                "Database connection failed: " . $e->getMessage(),
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
     * Get write connection
     */
    public function getWriteConnection(string $name = 'default'): PDO
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
     * Get query builder for write operations
     */
    public function writeTable(string $table, string $connection = 'default'): QueryBuilder
    {
        return new QueryBuilder($this->getWriteConnection($connection), $table);
    }

    /**
     * Execute a transaction
     */
    public function transaction(callable $callback, string $connection = 'default'): mixed
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
                if (isset($connectionConfig['read'])) {
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
                'write_connections' => 1,
                'read_connections' => count($this->readConnections[$name]),
                'current_read_index' => $this->readConnectionIndexes[$name]
            ];
        }

        return $stats;
    }
}