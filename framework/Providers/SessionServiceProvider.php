<?php
/**
 * Session Service Provider
 * Registers session management services
 */
declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\ServiceProvider;
use Framework\Core\SessionManager;
use Framework\Core\SessionManagerInterface;

class SessionServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Register SessionManager as singleton
        $container->singleton(SessionManagerInterface::class, function ($container) {
            $config = $container->get('config');
            return new SessionManager($config);
        });

        // Alias for easier access
        $container->bind('session', function ($container) {
            return $container->get(SessionManagerInterface::class);
        });
    }

    public function boot(Container $container): void
    {
        $config = $container->get('config');
        $sessionConfig = $config['security']['session'] ?? [];

        if ($sessionConfig['auto_start'] ?? false) {
            $session = $container->get(SessionManagerInterface::class);
            $session->start();
        }
    }
}