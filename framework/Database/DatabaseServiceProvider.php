<?php

declare(strict_types=1);

namespace Framework\Database;

use Framework\Core\AbstractServiceProvider;
use Framework\Core\ConfigValidation;

/**
 * Database Service Provider - Registriert Database Services im Framework
 *
 * BEREINIGT: Eliminiert Config-Duplikation, vereinfachte Konfiguration
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

        // Config-Validierung
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
            $config = $this->loadAndValidateConfig('database');

            // Direkte Verwendung der Config ohne Duplikation
            $connectionManager = new ConnectionManager();
            $connectionManager->loadFromConfig($config);

            return $connectionManager;
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
}