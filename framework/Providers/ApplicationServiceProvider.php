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


class ApplicationServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {

    }

    public function boot(Container $container): void
    {
        // Application-specific boot logic can go here
    }
}