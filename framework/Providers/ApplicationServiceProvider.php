<?php
/**
 * Application Service Provider
 * Application-specific services and business logic
 */

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\ServiceProvider;

class ApplicationServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // For now, register any custom application services
        $this->registerGameServices($container);
        $this->registerUserServices($container);
        $this->registerBusinessServices($container);
    }

    public function boot(Container $container): void
    {
        $config = $container->get('config');

        // Initialize game settings
        $this->initializeGameSettings($container, $config);

        // Warm up container cache in production
        if (!$config['app']['debug']) {
            $this->warmUpServices($container);
        }

        // Log application readiness
        $this->logApplicationStatus($container, $config);
    }

    /**
     * Register game-specific services
     */
    private function registerGameServices(Container $container): void
    {
        // Future: Game-specific services
        // $container->singleton(GameEngine::class, function($container) {
        //     return new GameEngine($container->get('db'));
        // });

        // $container->singleton(MatchService::class, function($container) {
        //     return new MatchService($container->get('db'));
        // });

        // $container->alias('game', GameEngine::class);
    }

    /**
     * Register user management services
     */
    private function registerUserServices(Container $container): void
    {
        // Future: User management services
        // $container->singleton(UserRepository::class, function($container) {
        //     return new UserRepository($container->get('db'));
        // });

        // $container->singleton(AuthService::class, function($container) {
        //     return new AuthService(
        //         $container->get(UserRepository::class),
        //         $container->get('session'),
        //         $container->get('hasher')
        //     );
        // });

        // $container->alias('auth', AuthService::class);
    }

    /**
     * Register business logic services
     */
    private function registerBusinessServices(Container $container): void
    {
        // Future: Business logic services
        // $container->singleton(TeamManager::class, function($container) {
        //     return new TeamManager($container->get('db'));
        // });

        // $container->singleton(TransferService::class, function($container) {
        //     return new TransferService($container->get('db'));
        // });
    }

    /**
     * Initialize game settings
     */
    private function initializeGameSettings(Container $container, array $config): void
    {
    }

    /**
     * Warm up frequently used services
     */
    private function warmUpServices(Container $container): void
    {
        $services = [
            'logger',
            'session',
            'db',
            'validator',
            'hasher',
            'templates',
            'email',
        ];

        $warmedServices = [];
        foreach ($services as $service) {
            try {
                $container->get($service);
                $warmedServices[] = $service;
            } catch (\Throwable $e) {
                $container->get('logger')->warning("Failed to warm up service: $service", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Save container cache
        try {
            $container->saveCache();
            $container->get('logger')->debug('Container cache saved', [
                'warmed_services' => $warmedServices
            ]);
        } catch (\Throwable $e) {
            $container->get('logger')->warning('Failed to save container cache', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log application status
     */
    private function logApplicationStatus(Container $container, array $config): void
    {
        $stats = $container->getStats();

        $container->get('logger')->info('Application initialized successfully', [
            'environment' => $config['app']['environment'] ?? 'unknown',
            'debug_mode' => $config['app']['debug'] ?? false,
            'services_registered' => $stats['bindings'] ?? 0,
            'singletons_registered' => $stats['singletons'] ?? 0,
            'aliases_registered' => $stats['aliases'] ?? 0,
            'cache_enabled' => $stats['cache_enabled'] ?? false,
        ]);
    }
}