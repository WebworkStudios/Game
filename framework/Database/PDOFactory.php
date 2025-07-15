<?php

declare(strict_types=1);

namespace Framework\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * MySQL PDO Factory - Erstellt und konfiguriert MySQL-PDO-Verbindungen
 */
class PDOFactory
{
    private const int DEFAULT_TIMEOUT = 5;
    private const int MAX_RETRY_ATTEMPTS = 3;
    private const int RETRY_DELAY_MS = 100;

    /**
     * Erstellt MySQL PDO-Verbindung aus Konfiguration
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
                    array_merge($config->getPdoOptions(), [
                        PDO::ATTR_TIMEOUT => self::DEFAULT_TIMEOUT,
                    ])
                );

                self::configureMysql($pdo, $config);
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
            "Failed to connect to MySQL database after {$attempts} attempts: " .
            $lastException?->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Testet MySQL-Datenbankverbindung
     */
    public static function testConnection(DatabaseConfig $config): bool
    {
        try {
            $pdo = self::create($config);

            // MySQL-spezifische Test-Query
            $result = $pdo->query('SELECT 1 as test');
            $data = $result->fetch(PDO::FETCH_ASSOC);

            return $data['test'] === 1;

        } catch (\Exception) {
            return false;
        }
    }

    /**
     * MySQL-spezifische Konfiguration nach Connection
     */
    private static function configureMysql(PDO $pdo, DatabaseConfig $config): void
    {
        // SQL Mode für strikte Validierung und bessere Datenintegrität
        $pdo->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER'");

        // Charset explizit setzen mit Kollation
        $pdo->exec("SET NAMES {$config->charset} COLLATE {$config->charset}_unicode_ci");

        // Timezone auf UTC für konsistente Zeitwerte
        $pdo->exec("SET time_zone = '+00:00'");

        // Session-Variablen für bessere Performance
        $pdo->exec("SET SESSION query_cache_type = ON");

        // InnoDB-spezifische Optimierungen
        $pdo->exec("SET SESSION innodb_lock_wait_timeout = 50");

        // Binäre Logs für Replikation (falls aktiviert)
        $pdo->exec("SET SESSION binlog_format = 'ROW'");

        // Autocommit sicherstellen (Standard, aber explizit)
        $pdo->exec("SET autocommit = 1");
    }

    /**
     * Holt MySQL-Version
     */
    public static function getMySQLVersion(PDO $pdo): string
    {
        try {
            $stmt = $pdo->query('SELECT VERSION() as version');
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['version'] ?? 'Unknown';

        } catch (\Exception) {
            return 'Unknown';
        }
    }

    /**
     * Prüft MySQL-Features und Konfiguration
     */
    public static function checkMySQLCapabilities(PDO $pdo): array
    {
        $capabilities = [
            'version' => self::getMySQLVersion($pdo),
            'charset' => 'unknown',
            'timezone' => 'unknown',
            'sql_mode' => 'unknown',
            'engines' => [],
            'innodb_version' => 'unknown',
        ];

        try {
            // Charset prüfen
            $stmt = $pdo->query("SELECT @@character_set_database as charset");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $capabilities['charset'] = $result['charset'] ?? 'unknown';

            // Timezone prüfen
            $stmt = $pdo->query("SELECT @@time_zone as timezone");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $capabilities['timezone'] = $result['timezone'] ?? 'unknown';

            // SQL Mode prüfen
            $stmt = $pdo->query("SELECT @@sql_mode as sql_mode");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $capabilities['sql_mode'] = $result['sql_mode'] ?? 'unknown';

            // Verfügbare Storage Engines
            $stmt = $pdo->query("SHOW ENGINES");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['Support'] === 'YES' || $row['Support'] === 'DEFAULT') {
                    $capabilities['engines'][] = $row['Engine'];
                }
            }

            // InnoDB Version (falls verfügbar)
            $stmt = $pdo->query("SELECT @@innodb_version as innodb_version");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $capabilities['innodb_version'] = $result['innodb_version'] ?? 'unknown';

        } catch (\Exception $e) {
            // Bei Fehlern werden Defaults beibehalten
            error_log("MySQL capability check failed: " . $e->getMessage());
        }

        return $capabilities;
    }

    /**
     * Optimiert MySQL-Connection für Fußballmanager-Workload
     */
    public static function optimizeForGameWorkload(PDO $pdo): void
    {
        try {
            // Optimierungen für Read-Heavy Workload (typisch für Fußballmanager)
            $pdo->exec("SET SESSION query_cache_limit = 1048576");      // 1MB Cache Limit
            $pdo->exec("SET SESSION sort_buffer_size = 2097152");       // 2MB Sort Buffer
            $pdo->exec("SET SESSION read_buffer_size = 131072");        // 128KB Read Buffer
            $pdo->exec("SET SESSION read_rnd_buffer_size = 262144");    // 256KB Random Read Buffer

            // Join Buffer für komplexe Queries (Spieler, Teams, Statistiken)
            $pdo->exec("SET SESSION join_buffer_size = 262144");        // 256KB Join Buffer

            // Temp Table Size für Aggregationen
            $pdo->exec("SET SESSION tmp_table_size = 16777216");        // 16MB Temp Tables
            $pdo->exec("SET SESSION max_heap_table_size = 16777216");   // 16MB Heap Tables

        } catch (\Exception $e) {
            // Optimierungen sind optional - bei Fehlern weitermachen
            error_log("MySQL workload optimization failed: " . $e->getMessage());
        }
    }

    /**
     * Aktiviert MySQL-Performance-Monitoring
     */
    public static function enablePerformanceMonitoring(PDO $pdo): void
    {
        try {
            // Performance Schema aktivieren (falls verfügbar)
            $pdo->exec("SET SESSION performance_schema = ON");

            // Slow Query Log für Performance-Analyse
            $pdo->exec("SET SESSION slow_query_log = ON");
            $pdo->exec("SET SESSION long_query_time = 1.0"); // Queries > 1s loggen

            // Query-Statistiken aktivieren
            $pdo->exec("SET SESSION general_log = OFF"); // General Log aus (Performance)

        } catch (\Exception $e) {
            // Monitoring ist optional
            error_log("MySQL performance monitoring setup failed: " . $e->getMessage());
        }
    }

    /**
     * Prüft und repariert MySQL-Tables (Wartung)
     */
    public static function checkAndRepairTables(PDO $pdo, array $tables): array
    {
        $results = [];

        foreach ($tables as $table) {
            try {
                // Table prüfen
                $stmt = $pdo->query("CHECK TABLE `{$table}`");
                $checkResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $results[$table] = [
                    'check' => $checkResult,
                    'repair' => null,
                    'status' => 'ok'
                ];

                // Bei Problemen reparieren
                foreach ($checkResult as $row) {
                    if ($row['Msg_type'] === 'error' || $row['Msg_type'] === 'warning') {
                        $stmt = $pdo->query("REPAIR TABLE `{$table}`");
                        $repairResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $results[$table]['repair'] = $repairResult;
                        $results[$table]['status'] = 'repaired';
                        break;
                    }
                }

            } catch (\Exception $e) {
                $results[$table] = [
                    'check' => null,
                    'repair' => null,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}