<?php
/**
 * Database Service Provider
 * Database connections and data validation services
 *
 * File: framework/Providers/DatabaseServiceProvider.php
 * Directory: /framework/Providers/
 */

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\ServiceProvider;
use Framework\Database\ConnectionPool;
use Framework\Validation\Validator;
use ReflectionException;

class DatabaseServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Database Connection Pool
        $container->singleton(ConnectionPool::class, function ($container) {
            $config = $container->get('config');
            return new ConnectionPool($config['database']);
        });

        // Alias for easier access
        $container->alias('db', ConnectionPool::class);

        // Validator (depends on database)
        $container->singleton(Validator::class, function ($container) {
            return new Validator($container->get(ConnectionPool::class));
        });

        $container->alias('validator', Validator::class);
    }

    /**
     * @throws ReflectionException
     */
    public function boot(Container $container): void
    {
        $config = $container->get('config');

        // Test database connection in development
        if ($config['app']['debug'] && ($config['database']['test_on_boot'] ?? false)) {
            $this->testDatabaseConnection($container);
        }

        // Log database configuration
        $this->logDatabaseConfig($container);
    }

    /**
     * Test database connection during boot
     */
    private function testDatabaseConnection(Container $container): void
    {
        try {
            $db = $container->get(ConnectionPool::class);
            $db->select('SELECT 1 as test');

            $container->get('logger')->debug('Database connection verified');

            // Test connection pool statistics
            $stats = $db->getStats();
            $container->get('logger')->debug('Database connection pool stats', $stats);

        } catch (\Throwable $e) {
            $container->get('logger')->warning('Database connection failed during boot', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Log database configuration details
     */
    private function logDatabaseConfig(Container $container): void
    {
        $config = $container->get('config');
        $dbConfig = $config['database'];

        $logData = [
            'default_connection' => $dbConfig['default'] ?? 'mysql',
            'connections_configured' => count($dbConfig['connections'] ?? []),
        ];

        // Add connection details without sensitive data
        foreach ($dbConfig['connections'] ?? [] as $name => $connectionConfig) {
            $writeConfig = $connectionConfig['write'] ?? [];
            $readConfigs = $connectionConfig['read'] ?? [];

            $logData['connections'][$name] = [
                'write_host' => $writeConfig['host'] ?? 'unknown',
                'write_port' => $writeConfig['port'] ?? 'unknown',
                'write_database' => $writeConfig['database'] ?? 'unknown',
                'read_replicas' => count($readConfigs),
                'ssl_enabled' => !empty($writeConfig['ssl']['ca']),
            ];
        }

        $container->get('logger')->debug('Database service initialized', $logData);
    }
}