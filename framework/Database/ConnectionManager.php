<?php

declare(strict_types=1);

namespace Framework\Database;

use Framework\Database\Enums\ConnectionType;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * MySQL Connection Manager - Verwaltet MySQL-Verbindungen mit Read/Write-Split
 */
class ConnectionManager
{
    private const string DEFAULT_CONNECTION = 'default';

    /** @var array<string, DatabaseConfig[]> */
    private array $configurations = [];

    /** @var array<string, PDO> */
    private array $connections = [];

    private bool $debugMode = false;
    private bool $gameOptimizationsEnabled = true;
    private bool $performanceMonitoringEnabled = false;

    /**
     * Lädt Konfiguration aus Array
     */
    public function loadFromConfig(array $config): void
    {
        foreach ($config as $name => $connectionConfigs) {
            // Single connection config
            if (isset($connectionConfigs['host'])) {
                $this->addConnection($name, DatabaseConfig::fromArray($connectionConfigs));
                continue;
            }

            // Multiple connection configs (für Load Balancing)
            foreach ($connectionConfigs as $connConfig) {
                $this->addConnection($name, DatabaseConfig::fromArray($connConfig));
            }
        }
    }

    /**
     * Fügt MySQL-Konfiguration hinzu
     */
    public function addConnection(string $name, DatabaseConfig $config): void
    {
        if (!isset($this->configurations[$name])) {
            $this->configurations[$name] = [];
        }

        $this->configurations[$name][] = $config;
    }

    /**
     * Holt Read-Connection (Load Balancing aware)
     */
    public function getReadConnection(string $name = self::DEFAULT_CONNECTION): PDO
    {
        return $this->getConnection($name, ConnectionType::READ);
    }

    /**
     * Holt Write-Connection
     */
    public function getWriteConnection(string $name = self::DEFAULT_CONNECTION): PDO
    {
        return $this->getConnection($name, ConnectionType::WRITE);
    }

    /**
     * Holt Verbindung für bestimmte Operation
     */
    public function getConnection(
        string         $name = self::DEFAULT_CONNECTION,
        ConnectionType $type = ConnectionType::WRITE
    ): PDO
    {
        if (!isset($this->configurations[$name])) {
            throw new InvalidArgumentException("Connection '{$name}' not configured");
        }

        $connectionKey = "{$name}:{$type->value}";

        // Bereits bestehende Verbindung wiederverwenden
        if (isset($this->connections[$connectionKey])) {
            return $this->connections[$connectionKey];
        }

        // Passende Konfiguration finden
        $config = $this->selectConfiguration($name, $type);

        // Neue MySQL-Verbindung erstellen
        $pdo = $this->createOptimizedMySQLConnection($config);
        $this->connections[$connectionKey] = $pdo;

        if ($this->debugMode) {
            error_log("MySQL connection created: {$connectionKey} ({$config->host}:{$config->port})");
        }

        return $pdo;
    }

    /**
     * Erstellt optimierte MySQL-Verbindung mit Game-spezifischen Einstellungen
     */
    private function createOptimizedMySQLConnection(DatabaseConfig $config): PDO
    {
        try {
            // MySQL PDO erstellen
            $pdo = PDOFactory::create($config);

            // Game-spezifische Optimierungen aktivieren
            if ($this->gameOptimizationsEnabled) {
                PDOFactory::optimizeForGameWorkload($pdo);
            }

            // Performance Monitoring aktivieren (optional)
            if ($this->performanceMonitoringEnabled) {
                PDOFactory::enablePerformanceMonitoring($pdo);
            }

            if ($this->debugMode) {
                $capabilities = PDOFactory::checkMySQLCapabilities($pdo);
                error_log("MySQL capabilities: " . json_encode($capabilities));
            }

            return $pdo;

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to create optimized MySQL connection to {$config->host}:{$config->port}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Wählt beste Konfiguration für Connection Type
     */
    private function selectConfiguration(string $name, ConnectionType $type): DatabaseConfig
    {
        $configs = $this->configurations[$name];
        $candidates = [];

        // Filtere nach Connection Type
        foreach ($configs as $config) {
            if ($config->type === $type || $config->type === ConnectionType::WRITE) {
                $candidates[] = $config;
            }
        }

        if (empty($candidates)) {
            throw new RuntimeException("No suitable MySQL connection found for '{$name}' with type '{$type->value}'");
        }

        // Weighted Random Selection für Load Balancing
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        return $this->weightedRandomSelection($candidates);
    }

    /**
     * Weighted Random Selection für Load Balancing
     */
    private function weightedRandomSelection(array $configs): DatabaseConfig
    {
        $totalWeight = array_sum(array_map(fn($config) => $config->weight, $configs));
        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($configs as $config) {
            $currentWeight += $config->weight;
            if ($random <= $currentWeight) {
                return $config;
            }
        }

        // Fallback (sollte nie erreicht werden)
        return $configs[0];
    }

    /**
     * Führt Callback in MySQL-Transaktion aus
     */
    public function transaction(callable $callback, string $name = self::DEFAULT_CONNECTION): mixed
    {
        $this->beginTransaction($name);

        try {
            $result = $callback($this);
            $this->commit($name);
            return $result;
        } catch (\Exception $e) {
            $this->rollback($name);
            throw $e;
        }
    }

    /**
     * Startet Transaktion auf Write-Connection
     */
    public function beginTransaction(string $name = self::DEFAULT_CONNECTION): bool
    {
        $connection = $this->getWriteConnection($name);
        return $connection->beginTransaction();
    }

    /**
     * Committed Transaktion
     */
    public function commit(string $name = self::DEFAULT_CONNECTION): bool
    {
        $connection = $this->getWriteConnection($name);
        return $connection->commit();
    }

    /**
     * Rollback Transaktion
     */
    public function rollback(string $name = self::DEFAULT_CONNECTION): bool
    {
        $connection = $this->getWriteConnection($name);
        return $connection->rollBack();
    }

    /**
     * Setzt Debug-Modus
     */
    public function setDebugMode(bool $debug): void
    {
        $this->debugMode = $debug;
    }

    /**
     * Aktiviert/Deaktiviert Game-spezifische Optimierungen
     */
    public function setGameOptimizations(bool $enabled): void
    {
        $this->gameOptimizationsEnabled = $enabled;
    }

    /**
     * Aktiviert/Deaktiviert Performance-Monitoring
     */
    public function setPerformanceMonitoring(bool $enabled): void
    {
        $this->performanceMonitoringEnabled = $enabled;
    }

    /**
     * Testet alle konfigurierten MySQL-Verbindungen
     */
    public function testAllConnections(): array
    {
        $results = [];

        foreach ($this->configurations as $name => $configs) {
            foreach ($configs as $index => $config) {
                $key = "{$name}[{$index}]";
                $testResult = PDOFactory::testConnection($config);

                $results[$key] = [
                    'config' => $config->toArray(),
                    'success' => $testResult,
                    'capabilities' => null,
                ];

                // Bei erfolgreicher Verbindung auch Capabilities testen
                if ($testResult) {
                    try {
                        $pdo = PDOFactory::create($config);
                        $results[$key]['capabilities'] = PDOFactory::checkMySQLCapabilities($pdo);
                    } catch (\Exception $e) {
                        $results[$key]['capabilities_error'] = $e->getMessage();
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Führt Wartung auf allen Tables durch
     */
    public function maintainTables(array $tables, string $connectionName = self::DEFAULT_CONNECTION): array
    {
        $connection = $this->getWriteConnection($connectionName);
        return PDOFactory::checkAndRepairTables($connection, $tables);
    }

    /**
     * Holt Performance-Statistiken aller Verbindungen
     */
    public function getPerformanceStats(): array
    {
        $stats = [];

        foreach ($this->connections as $key => $pdo) {
            try {
                // MySQL-spezifische Performance-Queries
                $stmt = $pdo->query("SHOW SESSION STATUS LIKE 'Questions'");
                $questions = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->query("SHOW SESSION STATUS LIKE 'Uptime'");
                $uptime = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->query("SHOW SESSION STATUS LIKE 'Slow_queries'");
                $slowQueries = $stmt->fetch(PDO::FETCH_ASSOC);

                $stats[$key] = [
                    'questions' => (int)($questions['Value'] ?? 0),
                    'uptime' => (int)($uptime['Value'] ?? 0),
                    'slow_queries' => (int)($slowQueries['Value'] ?? 0),
                    'qps' => 0, // Questions per second
                ];

                if ($stats[$key]['uptime'] > 0) {
                    $stats[$key]['qps'] = round($stats[$key]['questions'] / $stats[$key]['uptime'], 2);
                }

            } catch (\Exception $e) {
                $stats[$key] = [
                    'error' => $e->getMessage()
                ];
            }
        }

        return $stats;
    }

    /**
     * Optimiert alle bestehenden Verbindungen für bessere Performance
     */
    public function optimizeAllConnections(): array
    {
        $results = [];

        foreach ($this->connections as $key => $pdo) {
            try {
                PDOFactory::optimizeForGameWorkload($pdo);
                $results[$key] = 'optimized';
            } catch (\Exception $e) {
                $results[$key] = 'failed: ' . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Holt detaillierte MySQL-Informationen
     */
    public function getMySQLInfo(string $connectionName = self::DEFAULT_CONNECTION): array
    {
        $connection = $this->getReadConnection($connectionName);

        $info = [
            'version' => PDOFactory::getMySQLVersion($connection),
            'capabilities' => PDOFactory::checkMySQLCapabilities($connection),
            'performance' => [],
            'tables' => [],
        ];

        try {
            // Table-Statistiken
            $stmt = $connection->query("
                SELECT 
                    table_name,
                    table_rows,
                    data_length,
                    index_length,
                    (data_length + index_length) as total_size
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY total_size DESC
            ");

            $info['tables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Performance-Variablen
            $stmt = $connection->query("
                SHOW VARIABLES WHERE Variable_name IN (
                    'innodb_buffer_pool_size',
                    'query_cache_size',
                    'max_connections',
                    'thread_cache_size'
                )
            ");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $info['performance'][$row['Variable_name']] = $row['Value'];
            }

        } catch (\Exception $e) {
            $info['info_error'] = $e->getMessage();
        }

        return $info;
    }

    /**
     * Schließt alle Verbindungen
     */
    public function closeAllConnections(): void
    {
        $this->connections = [];

        if ($this->debugMode) {
            error_log("All MySQL connections closed");
        }
    }

    /**
     * Holt alle aktiven Verbindungen (für Debugging)
     */
    public function getActiveConnections(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Prüft ob Game-Optimierungen aktiviert sind
     */
    public function isGameOptimizationsEnabled(): bool
    {
        return $this->gameOptimizationsEnabled;
    }

    /**
     * Prüft ob Performance-Monitoring aktiviert ist
     */
    public function isPerformanceMonitoringEnabled(): bool
    {
        return $this->performanceMonitoringEnabled;
    }
}