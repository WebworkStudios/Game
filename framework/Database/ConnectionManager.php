<?php


declare(strict_types=1);

namespace Framework\Database;

use Framework\Database\Enums\ConnectionType;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Connection Manager - Verwaltet mehrere Datenbankverbindungen
 */
class ConnectionManager
{
    private const string DEFAULT_CONNECTION = 'default';

    /** @var array<string, DatabaseConfig[]> */
    private array $configurations = [];

    /** @var array<string, PDO> */
    private array $connections = [];

    /** @var array<string, array{reads: int, writes: int}> */
    private array $statistics = [];

    private bool $debugMode = false;

    /**
     * Fügt Datenbank-Konfiguration hinzu
     */
    public function addConnection(string $name, DatabaseConfig $config): void
    {
        if (!isset($this->configurations[$name])) {
            $this->configurations[$name] = [];
        }

        $this->configurations[$name][] = $config;
        $this->statistics[$name] = ['reads' => 0, 'writes' => 0];
    }

    /**
     * Lädt Konfiguration aus Array
     */
    public function loadFromConfig(array $config): void
    {
        foreach ($config as $name => $connectionConfigs) {
            // Single connection config
            if (isset($connectionConfigs['driver'])) {
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
            $this->updateStatistics($name, $type);
            return $this->connections[$connectionKey];
        }

        // Passende Konfiguration finden
        $config = $this->selectConfiguration($name, $type);

        // Neue Verbindung erstellen
        $pdo = PDOFactory::create($config);
        $this->connections[$connectionKey] = $pdo;

        $this->updateStatistics($name, $type);

        if ($this->debugMode) {
            error_log("Database connection created: {$connectionKey} ({$config->host}:{$config->port})");
        }

        return $pdo;
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
     * Führt Callback in Transaktion aus
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
     * Setzt Debug-Modus
     */
    public function setDebugMode(bool $debug): void
    {
        $this->debugMode = $debug;
    }

    /**
     * Testet alle konfigurierten Verbindungen
     */
    public function testAllConnections(): array
    {
        $results = [];

        foreach ($this->configurations as $name => $configs) {
            foreach ($configs as $index => $config) {
                $key = "{$name}[{$index}]";
                $results[$key] = [
                    'config' => $config->toArray(),
                    'success' => PDOFactory::testConnection($config),
                ];
            }
        }

        return $results;
    }

    /**
     * Holt Verbindungsstatistiken
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * Schließt alle Verbindungen
     */
    public function closeAllConnections(): void
    {
        $this->connections = [];

        if ($this->debugMode) {
            error_log("All database connections closed");
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
            throw new RuntimeException("No suitable connection found for '{$name}' with type '{$type->value}'");
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
     * Aktualisiert Verbindungsstatistiken
     */
    private function updateStatistics(string $name, ConnectionType $type): void
    {
        if ($type === ConnectionType::READ) {
            $this->statistics[$name]['reads']++;
        } else {
            $this->statistics[$name]['writes']++;
        }
    }
}