<?php

declare(strict_types=1);

namespace Framework\Database;

use Framework\Core\AbstractServiceProvider;

/**
 * Database Service Provider - Registriert Database Services im Framework
 *
 * Vollständig migrierte Version mit AbstractServiceProvider und ConfigManager.
 * 90% weniger Code als das Original.
 */
class DatabaseServiceProvider extends AbstractServiceProvider
{
    private const string CONFIG_PATH = 'app/Config/database.php';
    private const array REQUIRED_KEYS = ['default', 'connections'];

    /**
     * Validiert Database-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        if (!extension_loaded('pdo')) {
            throw new \RuntimeException('PDO extension is required for database functionality');
        }

        if (!extension_loaded('pdo_mysql')) {
            throw new \RuntimeException('PDO MySQL driver is required');
        }
    }

    /**
     * Registriert alle Database Services
     */
    protected function registerServices(): void
    {
        $this->registerConnectionManager();
        $this->registerGrammar();
        $this->registerQueryBuilder();
    }

    /**
     * Registriert Connection Manager als Singleton
     */
    private function registerConnectionManager(): void
    {
        $this->singleton(ConnectionManager::class, function () {
            $config = $this->getConfig(
                configPath: self::CONFIG_PATH,
                defaultProvider: fn() => $this->getDefaultDatabaseConfig(),
                requiredKeys: self::REQUIRED_KEYS
            );

            return new ConnectionManager($config);
        });
    }

    /**
     * Registriert MySQL Grammar
     */
    private function registerGrammar(): void
    {
        $this->singleton(MySQLGrammar::class, function () {
            return new MySQLGrammar();
        });
    }

    /**
     * Registriert QueryBuilder Factory
     */
    private function registerQueryBuilder(): void
    {
        $this->transient(QueryBuilder::class, function () {
            return new QueryBuilder(
                connectionManager: $this->get(ConnectionManager::class),
                grammar: $this->get(MySQLGrammar::class),
                connectionName: 'default'
            );
        });

        // Named QueryBuilder Factory
        $this->singleton('query_builder_factory', function () {
            return function (string $connectionName = 'default') {
                return new QueryBuilder(
                    connectionManager: $this->get(ConnectionManager::class),
                    grammar: $this->get(MySQLGrammar::class),
                    connectionName: $connectionName
                );
            };
        });
    }

    /**
     * Bindet Database-Interfaces
     */
    protected function bindInterfaces(): void
    {
        // Hier können Repository Interfaces gebunden werden
        // $this->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    /**
     * Default Database Konfiguration
     */
    private function getDefaultDatabaseConfig(): array
    {
        return [
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'database' => 'kickerscup',
                    'username' => 'root',
                    'password' => '',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => 'InnoDB',
                    'options' => [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES => false,
                        \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode="STRICT_TRANS_TABLES"',
                    ],
                ],
                'game' => [
                    'driver' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'database' => 'kickers_game',
                    'username' => 'game_user',
                    'password' => 'game_password',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => 'game_',
                    'strict' => true,
                    'engine' => 'InnoDB',
                    'options' => [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES => false,
                    ],
                ],
            ],
            'query_log' => [
                'enabled' => false,
                'log_file' => 'storage/logs/queries.log',
                'log_slow_queries' => true,
                'slow_query_threshold' => 1000,
            ],
            'pool' => [
                'min_connections' => 1,
                'max_connections' => 10,
                'idle_timeout' => 600,
                'validation_query' => 'SELECT 1',
            ],
        ];
    }
}