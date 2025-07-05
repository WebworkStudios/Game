<?php

/**
 * Service Provider Interface
 * Interface for service providers that register services in the container
 *
 * File: framework/Core/ServiceProvider.php
 * Directory: /framework/Core/
 */

declare(strict_types=1);

namespace Framework\Core;

interface ServiceProvider
{
    /**
     * Register services in the container
     */
    public function register(Container $container): void;

    /**
     * Boot services after all providers are registered
     */
    public function boot(Container $container): void;
}