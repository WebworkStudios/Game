<?php
/**
 * Session Service Provider
 * Session management and authentication services
 *
 * File: framework/Providers/SessionServiceProvider.php
 * Directory: /framework/Providers/
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
        // Session Manager
        $container->singleton(SessionManagerInterface::class, function ($container) {
            $config = $container->get('config');
            return new SessionManager($config['security']['session'] ?? []);
        });

        // Alias for easier access
        $container->alias('session', SessionManagerInterface::class);
    }

    public function boot(Container $container): void
    {
        $config = $container->get('config');
        $sessionConfig = $config['security']['session'] ?? [];

        // Auto-start session if configured
        if ($sessionConfig['auto_start'] ?? false) {
            $session = $container->get(SessionManagerInterface::class);
            $session->start();

            $container->get('logger')->debug('Session auto-started', [
                'session_id' => $session->getId(),
                'session_name' => $session->getName()
            ]);
        }

        // Log session configuration
        $container->get('logger')->debug('Session service initialized', [
            'auto_start' => $sessionConfig['auto_start'] ?? false,
            'lifetime' => $sessionConfig['lifetime'] ?? 0,
            'secure' => $sessionConfig['secure'] ?? false,
            'httponly' => $sessionConfig['httponly'] ?? true,
            'samesite' => $sessionConfig['samesite'] ?? 'Strict'
        ]);
    }
}