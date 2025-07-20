<?php
declare(strict_types=1);

namespace Framework\Core;

use Framework\Cache\CacheServiceProvider;
use Framework\Database\DatabaseServiceProvider;
use Framework\Localization\LocalizationServiceProvider;
use Framework\Security\SecurityServiceProvider;
use Framework\Templating\TemplatingServiceProvider;

/**
 * ServiceProviderRegistry - UPDATED für LocalizationServiceProvider
 */
class ServiceProviderRegistry
{
    private const array DEFAULT_PROVIDERS = [
        CacheServiceProvider::class,
        DatabaseServiceProvider::class,
        SecurityServiceProvider::class,
        LocalizationServiceProvider::class,
        TemplatingServiceProvider::class,
    ];

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly ApplicationKernel $app
    ) {}

    public function registerAll(): void
    {
        foreach (self::DEFAULT_PROVIDERS as $providerClass) {
            $this->registerProvider($providerClass);
        }
    }

    private function registerProvider(string $providerClass): void
    {
        try {
            $provider = new $providerClass($this->container, $this->app);
            $provider->register();

        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to register service provider: {$providerClass}. Error: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    public function registerAdditional(array $providers): void
    {
        foreach ($providers as $providerClass) {
            $this->registerProvider($providerClass);
        }
    }
}