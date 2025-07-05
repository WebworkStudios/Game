<?php
/**
 * Framework Service Provider
 * Registers core framework services
 */

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\Logger;
use Framework\Core\ServiceProvider;
use Framework\Core\SessionManager;
use Framework\Core\SessionManagerInterface;
use Framework\Core\TemplateEngine;
use Framework\Email\EmailService;
use Framework\Security\CsrfProtection;
use Framework\Security\PasswordHasher;
use Framework\Security\RateLimiter;
use Framework\Validation\Validator;
use ReflectionException;

class FrameworkServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Template Engine
        $container->singleton(TemplateEngine::class, function () {
            return new TemplateEngine(__DIR__ . '/../../templates/');
        });

        // Logger
        $container->singleton('logger', function ($container) {
            $config = $container->get('config');
            return new Logger($config['logging']);
        });

        // Session Manager
        $container->singleton(SessionManagerInterface::class, function ($container) {
            $config = $container->get('config');
            return new SessionManager($config);
        });

        $container->bind('session', function ($container) {
            return $container->get(SessionManagerInterface::class);
        });

        // Password Hasher
        $container->singleton(PasswordHasher::class, function ($container) {
            $config = $container->get('config');
            return new PasswordHasher(
                $config['security']['password']['algorithm'],
                $config['security']['password']['options']
            );
        });

        // CSRF Protection
        $container->bind(CsrfProtection::class, function ($container) {
            $config = $container->get('config');
            $session = $container->get(SessionManagerInterface::class);

            return new CsrfProtection(
                $session,
                $config['security']['csrf']['token_name'],
                $config['security']['csrf']['token_lifetime']
            );
        });

        // Rate Limiter
        $container->singleton(RateLimiter::class, function ($container) {
            return new RateLimiter($container->get('db'));
        });

        // Email Service
        $container->singleton(EmailService::class, function ($container) {
            $config = $container->get('config');
            return new EmailService(
                $config['email'],
                $container->get(TemplateEngine::class)
            );
        });

        // Validator
        $container->singleton(Validator::class, function ($container) {
            return new Validator($container->get('db'));
        });
    }

    /**
     * @throws ReflectionException
     */
    public function boot(Container $container): void
    {
        // Set logger correlation ID from request
        $logger = $container->get('logger');

        if (isset($_SERVER['HTTP_X_CORRELATION_ID'])) {
            $logger->setCorrelationId($_SERVER['HTTP_X_CORRELATION_ID']);
        }

        $config = $container->get('config');
        $sessionConfig = $config['security']['session'] ?? [];

        if ($sessionConfig['auto_start'] ?? false) {
            $session = $container->get(SessionManagerInterface::class);
            $session->start();
        }
    }
}