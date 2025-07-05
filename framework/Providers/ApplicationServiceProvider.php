<?php

/**
 * Application Service Provider
 * Registers application-specific services
 *
 * File: framework/Providers/ApplicationServiceProvider.php
 * Directory: /framework/Providers/
 */

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\ServiceProvider;
use Player\Domain\PlayerFactory;
use Registration\Domain\RegistrationService;
use Registration\Responder\RegisterResponder;

class ApplicationServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Registration Service
        $container->singleton(RegistrationService::class, function ($container) {
            return new RegistrationService(
                $container->get('db'),
                $container->get(\User\Domain\UserRepository::class),
                $container->get(\Team\Domain\TeamRepository::class),
                $container->get(\Player\Domain\PlayerRepository::class),
                $container->get(\League\Domain\LeagueRepository::class),
                $container->get(\Player\Domain\PlayerFactory::class),
                $container->get(\Framework\Security\PasswordHasher::class),
                $container->get(\Framework\Email\EmailService::class),
                $container->get('logger')
            );
        });

        // Registration Responder
        $container->singleton(RegisterResponder::class, function ($container) {
            return new RegisterResponder($container->get(\Framework\Core\TemplateEngine::class));
        });

        // Player Factory
        $container->singleton(PlayerFactory::class, function ($container) {
            return new PlayerFactory();
        });
    }

    public function boot(Container $container): void
    {
        // Application-specific boot logic can go here
    }
}