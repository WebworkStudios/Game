<?php


declare(strict_types=1);

namespace Framework\Database;

use Framework\Database\Enums\ConnectionType;
use Framework\Database\Enums\DatabaseDriver;
use InvalidArgumentException;

/**
 * Database Connection Configuration
 */
readonly class DatabaseConfig
{
    public readonly int $port;
    private const array DEFAULT_OPTIONS = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    /**
     * @param DatabaseDriver $driver Database Driver
     * @param string $host Database Host
     * @param int $port Database Port
     * @param string $database Database Name
     * @param string $username Username
     * @param string $password Password
     * @param string $charset Character Set
     * @param array<int, mixed> $options PDO Options
     * @param ConnectionType $type Connection Type (read/write)
     * @param int $weight Load Balancing Weight
     */
    public function __construct(
        public DatabaseDriver $driver,
        public string $host = 'localhost',
        int $port = 0, // Kein public hier!
        public string $database = '',
        public string $username = '',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public array $options = [],
        public ConnectionType $type = ConnectionType::WRITE,
        public int $weight = 1,
    ) {
        // Port wird hier einmalig gesetzt
        $this->port = $port === 0 ? $this->driver->getDefaultPort() : $port;

        if ($this->driver->requiresHost() && empty($this->host)) {
            throw new InvalidArgumentException("Host is required for {$this->driver->value} driver");
        }

        if (empty($this->database)) {
            throw new InvalidArgumentException("Database name is required");
        }

        if ($this->weight < 1) {
            throw new InvalidArgumentException("Weight must be at least 1");
        }
    }

    /**
     * Erstellt DSN String für PDO
     */
    public function getDsn(): string
    {
        return match ($this->driver) {
            DatabaseDriver::MYSQL => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->host,
                $this->port,
                $this->database,
                $this->charset
            ),
            DatabaseDriver::POSTGRESQL, DatabaseDriver::PGSQL => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $this->host,
                $this->port,
                $this->database
            ),
            DatabaseDriver::SQLITE => sprintf(
                'sqlite:%s',
                $this->database
            ),
        };
    }

    /**
     * Holt finale PDO Options
     */
    public function getOptions(): array
    {
        return array_merge(self::DEFAULT_OPTIONS, $this->options);
    }

    /**
     * Erstellt Konfiguration aus Array
     */
    public static function fromArray(array $config): self
    {
        return new self(
            driver: DatabaseDriver::from($config['driver'] ?? 'mysql'),
            host: $config['host'] ?? 'localhost',
            port: $config['port'] ?? 0,
            database: $config['database'] ?? '',
            username: $config['username'] ?? '',
            password: $config['password'] ?? '',
            charset: $config['charset'] ?? 'utf8mb4',
            options: $config['options'] ?? [],
            type: isset($config['type']) ? ConnectionType::from($config['type']) : ConnectionType::WRITE,
            weight: $config['weight'] ?? 1,
        );
    }

    /**
     * Konvertiert zu Array (für Debugging)
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver->value,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => '***',
            'charset' => $this->charset,
            'type' => $this->type->value,
            'weight' => $this->weight,
            'dsn' => $this->getDsn(),
        ];
    }
}