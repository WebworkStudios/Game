<?php
/**
 * Routing Service Provider
 * Router and routing services
 *
 * File: framework/Providers/RoutingServiceProvider.php
 * Directory: /framework/Providers/
 */

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\Router;
use Framework\Core\ServiceProvider;

class RoutingServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Router
        $container->singleton(Router::class, function ($container) {
            return new Router($container);
        });

        // Alias for easier access
        $container->alias('router', Router::class);
    }

    public function boot(Container $container): void
    {
        $config = $container->get('config');

        // Log routing configuration
        $this->logRoutingConfig($container, $config);

        // Optionally warm up routes in production
        if (!$config['app']['debug']) {
            $this->warmUpRoutes($container);
        }
    }

    /**
     * Log routing configuration
     */
    private function logRoutingConfig(Container $container, array $config): void
    {
        $routeCacheConfig = $config['performance']['route_cache'] ?? [];

        $logData = [
            'cache_enabled' => $routeCacheConfig['enabled'] ?? false,
            'cache_path' => $routeCacheConfig['path'] ?? 'not_set',
            'auto_invalidate' => $routeCacheConfig['auto_invalidate'] ?? false,
        ];

        $container->get('logger')->debug('Routing service initialized', $logData);
    }

    /**
     * Warm up routes in production
     */
    private function warmUpRoutes(Container $container): void
    {
        try {
            $router = $container->get(Router::class);
            $cacheStatus = $router->getCacheStatus();

            if ($cacheStatus['enabled'] && !$cacheStatus['exists']) {
                // Cache will be built on first router access
                $container->get('logger')->debug('Route cache will be built on first access');
            } elseif ($cacheStatus['exists'] && $cacheStatus['valid']) {
                $routes = $router->getRoutes();
                $totalRoutes = array_sum(array_map('count', $routes));

                $container->get('logger')->debug('Routes loaded from cache', [
                    'total_routes' => $totalRoutes,
                    'cache_size' => $cacheStatus['size'],
                    'cache_created' => $cacheStatus['created_at']
                ]);
            }
        } catch (\Throwable $e) {
            $container->get('logger')->warning('Route warm-up failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}