<?php
declare(strict_types=1);

namespace Framework\Database;

use Framework\Core\Application;
use Framework\Core\ServiceContainer;
use Framework\Database\Enums\DatabaseDriver;
use InvalidArgumentException;

/**
 * Database Service Provider - Registriert Database Services im Framework
 */
class DatabaseServiceProvider
{
    private const string DEFAULT_CONFIG_PATH = 'app/Config/database.php';

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly Application      $app,
    )
    {
    }

    /**
     * Erstellt Standard-Konfigurationsdatei
     */
    public static function publishConfig(string $basePath): bool
    {
        $configPath = $basePath . '/' . self::DEFAULT_CONFIG_PATH;
        $configDir = dirname($configPath);

        if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
            return false;
        }

        $content = <<<'PHP'
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    */
    'default' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'kickerscup', // Change this to your database name
        'username' => 'root',
        'password' => '', // Add your database password
        'charset' => 'utf8mb4',
        'type' => 'write',
        'weight' => 1,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Analytics Database (Example for second connection)
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'driver' => 'postgresql',
        'host' => 'localhost',
        'port' => 5432,
        'database' => 'analytics',
        'username' => 'postgres',
        'password' => '',
        'type' => 'read',
        'weight' => 1,
    ],
];
PHP;

        return file_put_contents($configPath, $content) !== false;
    }

    /**
     * Registriert alle Database Services
     */
    public function register(): void
    {
        $this->registerConnectionManager();
        $this->registerQueryBuilder();
        $this->registerGrammar();
        $this->bindInterfaces();
    }

    /**
     * Registriert ConnectionManager als Singleton
     */
    private function registerConnectionManager(): void
    {
        $this->container->singleton(ConnectionManager::class, function (ServiceContainer $container) {
            $manager = new ConnectionManager();

            // Konfiguration laden
            $config = $this->loadDatabaseConfig();
            $manager->loadFromConfig($config);

            // Debug-Modus aus Application übernehmen
            if (method_exists($this->app, 'isDebug') && $this->app->isDebug()) {
                $manager->setDebugMode(true);
            }

            return $manager;
        });
    }

    /**
     * Lädt Database-Konfiguration
     */
    private function loadDatabaseConfig(): array
    {
        $configPath = $this->app->getBasePath() . '/' . self::DEFAULT_CONFIG_PATH;

        if (!file_exists($configPath)) {
            throw new InvalidArgumentException("Database config not found: {$configPath}");
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new InvalidArgumentException('Database config must return array');
        }

        return $config;
    }

    /**
     * Registriert QueryBuilder Factory
     */
    private function registerQueryBuilder(): void
    {
        $this->container->transient(QueryBuilder::class, function (ServiceContainer $container) {
            return new QueryBuilder(
                connectionManager: $container->get(ConnectionManager::class),
                grammar: $container->get(SqlGrammar::class),
                connectionName: 'default'
            );
        });

        // Named QueryBuilder Factory
        $this->container->singleton('query_builder_factory', function (ServiceContainer $container) {
            return function (string $connectionName = 'default') use ($container) {
                return new QueryBuilder(
                    connectionManager: $container->get(ConnectionManager::class),
                    grammar: $container->get(SqlGrammar::class),
                    connectionName: $connectionName
                );
            };
        });
    }

    /**
     * Registriert SQL Grammar
     */
    private function registerGrammar(): void
    {
        $this->container->singleton(SqlGrammar::class, function () {
            $config = $this->loadDatabaseConfig();
            $defaultDriver = DatabaseDriver::from($config['default']['driver'] ?? 'mysql');

            return new SqlGrammar($defaultDriver);
        });
    }

    /**
     * Bindet Interfaces (für zukünftige Repository Pattern)
     */
    private function bindInterfaces(): void
    {
        // Placeholder für Repository Interfaces
        // $this->container->bind(UserRepositoryInterface::class, UserRepository::class);
    }
}