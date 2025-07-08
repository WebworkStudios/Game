<?php


declare(strict_types=1);

namespace Framework\Database;

use Framework\Database\Enums\DatabaseDriver;
use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO Factory - Erstellt und konfiguriert PDO-Verbindungen
 */
class PDOFactory
{
    private const int DEFAULT_TIMEOUT = 5;
    private const int MAX_RETRY_ATTEMPTS = 3;
    private const int RETRY_DELAY_MS = 100;

    /**
     * Erstellt PDO-Verbindung aus Konfiguration
     */
    public static function create(DatabaseConfig $config): PDO
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < self::MAX_RETRY_ATTEMPTS) {
            try {
                $pdo = new PDO(
                    $config->getDsn(),
                    $config->username,
                    $config->password,
                    array_merge($config->getOptions(), [
                        PDO::ATTR_TIMEOUT => self::DEFAULT_TIMEOUT,
                    ])
                );

                self::configurePDO($pdo, $config);
                return $pdo;

            } catch (PDOException $e) {
                $lastException = $e;
                $attempts++;

                if ($attempts < self::MAX_RETRY_ATTEMPTS) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempts); // Exponential backoff
                }
            }
        }

        throw new RuntimeException(
            "Failed to connect to database after {$attempts} attempts: " . $lastException?->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Konfiguriert PDO-Instanz nach Erstellung
     */
    private static function configurePDO(PDO $pdo, DatabaseConfig $config): void
    {
        // Driver-spezifische Konfiguration
        match ($config->driver) {
            DatabaseDriver::MYSQL => self::configureMysql($pdo, $config),
            DatabaseDriver::POSTGRESQL => self::configurePostgreSQL($pdo),
            DatabaseDriver::SQLITE => self::configureSQLite($pdo),
        };
    }

    /**
     * MySQL-spezifische Konfiguration
     */
    private static function configureMysql(PDO $pdo, DatabaseConfig $config): void
    {
        // SQL Mode für strikte Validierung
        $pdo->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

        // Charset explizit setzen
        $pdo->exec("SET NAMES {$config->charset} COLLATE {$config->charset}_unicode_ci");

        // Timezone auf UTC
        $pdo->exec("SET time_zone = '+00:00'");

        // Session-Variablen für bessere Performance
        $pdo->exec("SET SESSION query_cache_type = ON");
    }

    /**
     * PostgreSQL-spezifische Konfiguration
     */
    private static function configurePostgreSQL(PDO $pdo): void
    {
        // Timezone auf UTC
        $pdo->exec("SET timezone = 'UTC'");

        // Client Encoding
        $pdo->exec("SET client_encoding = 'UTF8'");

        // DateStyle für konsistente Datumsformate
        $pdo->exec("SET datestyle = 'ISO, MDY'");
    }

    /**
     * SQLite-spezifische Konfiguration
     */
    private static function configureSQLite(PDO $pdo): void
    {
        // Foreign Key Support aktivieren
        $pdo->exec('PRAGMA foreign_keys = ON');

        // WAL Mode für bessere Concurrency
        $pdo->exec('PRAGMA journal_mode = WAL');

        // Optimierungen
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA cache_size = 1000');
        $pdo->exec('PRAGMA temp_store = MEMORY');
    }

    /**
     * Testet Datenbankverbindung
     */
    public static function testConnection(DatabaseConfig $config): bool
    {
        try {
            $pdo = self::create($config);

            // Einfache Query zum Testen
            $result = match ($config->driver) {
                DatabaseDriver::MYSQL => $pdo->query('SELECT 1'),
                DatabaseDriver::POSTGRESQL => $pdo->query('SELECT 1'),
                DatabaseDriver::SQLITE => $pdo->query('SELECT 1'),
            };

            return $result !== false;

        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Holt Datenbankversion
     */
    public static function getDatabaseVersion(PDO $pdo, DatabaseDriver $driver): string
    {
        try {
            $query = match ($driver) {
                DatabaseDriver::MYSQL => 'SELECT VERSION() as version',
                DatabaseDriver::POSTGRESQL => 'SELECT version() as version',
                DatabaseDriver::SQLITE => 'SELECT sqlite_version() as version',
            };

            $stmt = $pdo->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['version'] ?? 'Unknown';

        } catch (\Exception) {
            return 'Unknown';
        }
    }
}