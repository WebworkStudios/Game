<?php

declare(strict_types=1);

namespace Framework\Database;

use Framework\Core\AbstractServiceProvider;
use Framework\Core\ConfigValidation;

/**
 * Database Service Provider - Registriert Database Services im Framework
 *
 * BEREINIGT: Verwendet ConfigValidation Trait, eliminiert Code-Duplikation
 */
class DatabaseServiceProvider extends AbstractServiceProvider
{
    use ConfigValidation;

    /**
     * Validiert Database-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // PHP Extension Checks
        if (!extension_loaded('pdo')) {
            throw new \RuntimeException('PDO extension is required for database functionality');
        }

        if (!extension_loaded('pdo_mysql')) {
            throw new \RuntimeException('PDO MySQL driver is required');
        }

        // Config-Validierung (eliminiert die vorherige Duplikation)
        $this->ensureConfigExists('database');
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
            // Verwendet die neue loadAndValidateConfig() Methode
            $config = $this->loadAndValidateConfig('database');

            // Struktur-Anpassung für ConnectionManager
            $connectionManagerConfig = $this->adaptConfigForConnectionManager($config);

            return new ConnectionManager($connectionManagerConfig);
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
     * Adaptiert Config-Struktur für ConnectionManager
     */
    private function adaptConfigForConnectionManager(array $config): array
    {
        // Falls bereits im ConnectionManager-Format (mit 'connections' key)
        if (isset($config['connections'])) {
            return $config;
        }

        // Konvertiere app/Config/database.php Format zu ConnectionManager Format
        $adapted = [
            'default' => 'mysql',
            'connections' => []
        ];

        // Hauptverbindung als 'mysql' Connection
        if (isset($config['default'])) {
            $adapted['connections']['mysql'] = array_merge($config['default'], [
                'driver' => 'mysql',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => 'InnoDB',
                'options' => [],
            ]);
        }

        return $adapted;
    }
}