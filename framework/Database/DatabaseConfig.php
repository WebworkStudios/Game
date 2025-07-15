<?php
declare(strict_types=1);

namespace Framework\Database;

use Framework\Database\Enums\ConnectionType;
use InvalidArgumentException;

/**
 * MySQL Database Connection Configuration
 */
readonly class DatabaseConfig
{
    private const array DEFAULT_OPTIONS = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    public readonly int $port;

    /**
     * @param string $host Database Host
     * @param int $port Database Port (default: 3306)
     * @param string $database Database Name
     * @param string $username Username
     * @param string $password Password
     * @param string $charset Character Set (default: utf8mb4)
     * @param array<int, mixed> $options PDO Options
     * @param ConnectionType $type Connection Type (read/write)
     * @param int $weight Load Balancing Weight
     */
    public function __construct(
        public string         $host = 'localhost',
        int                   $port = 3306,
        public string         $database = '',
        public string         $username = '',
        public string         $password = '',
        public string         $charset = 'utf8mb4',
        public array          $options = [],
        public ConnectionType $type = ConnectionType::WRITE,
        public int            $weight = 1,
    )
    {
        $this->port = $port;

        if (empty($this->host)) {
            throw new InvalidArgumentException("Host is required for MySQL connection");
        }

        if (empty($this->database)) {
            throw new InvalidArgumentException("Database name is required");
        }

        if ($this->weight < 1) {
            throw new InvalidArgumentException("Weight must be at least 1");
        }
    }

    /**
     * Erstellt Konfiguration aus Array
     */
    public static function fromArray(array $config): self
    {
        return new self(
            host: $config['host'] ?? 'localhost',
            port: $config['port'] ?? 3306,
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
     * Erstellt MySQL PDO DSN
     */
    public function getDsn(): string
    {
        return "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset={$this->charset}";
    }

    /**
     * Holt PDO Options mit Defaults
     */
    public function getPdoOptions(): array
    {
        return array_merge(self::DEFAULT_OPTIONS, $this->options);
    }

    /**
     * Konvertiert zu Array für Debug/Export
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => '***', // Password verstecken
            'charset' => $this->charset,
            'type' => $this->type->value,
            'weight' => $this->weight,
        ];
    }
}