<?php
/**
 * Database Service Provider
 * Registers database and repository services
 *
 * File: framework/Providers/DatabaseServiceProvider.php
 * Directory: /framework/Providers/
 */

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\ServiceProvider;
use Framework\Database\ConnectionPool;
use League\Domain\LeagueRepository;
use Player\Domain\PlayerRepository;
use Team\Domain\TeamRepository;
use User\Domain\UserRepository;

class DatabaseServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Database Connection Pool
        $container->singleton('db', function ($container) {
            $config = $container->get('config');
            return new ConnectionPool($config['database']);
        });

        // Repositories
        $container->singleton(UserRepository::class, function ($container) {
            return new UserRepository($container->get('db'));
        });

        $container->singleton(TeamRepository::class, function ($container) {
            return new TeamRepository($container->get('db'));
        });

        $container->singleton(PlayerRepository::class, function ($container) {
            return new PlayerRepository($container->get('db'));
        });

        $container->singleton(LeagueRepository::class, function ($container) {
            return new LeagueRepository($container->get('db'));
        });
    }

    public function boot(Container $container): void
    {
        // No automatic connection testing during boot
        // Database connections are tested lazily when first accessed
        // This prevents blocking the application startup with connection errors

        // Optional: Log that database service is ready
        if ($container->has('logger')) {
            $container->get('logger')->debug('Database service provider booted successfully');
        }
    }
}