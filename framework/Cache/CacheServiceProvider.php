<?php

declare(strict_types=1);

namespace Framework\Cache;

use Framework\Cache\Drivers\ApcuCacheDriver;
use Framework\Cache\Drivers\FileCacheDriver;
use Framework\Cache\Drivers\RedisCacheDriver;
use Framework\Core\AbstractServiceProvider;
use Framework\Core\ConfigValidation;

/**
 * CacheServiceProvider - Registriert die neue Cache-Abstraktion
 */
class CacheServiceProvider extends AbstractServiceProvider
{
    use ConfigValidation;

    protected function validateDependencies(): void
    {
        $this->ensureConfigExists('cache');
        $this->validateCacheDirectories();
    }

    protected function registerServices(): void
    {
        $this->registerCacheDrivers();
        $this->registerCacheManager();
    }

    /**
     * Registriert alle Cache-Driver
     */
    private function registerCacheDrivers(): void
    {
        // APCu Driver
        $this->singleton(ApcuCacheDriver::class, function () {
            $config = $this->loadAndValidateConfig('cache');
            return new ApcuCacheDriver($config['stores']['apcu']['prefix'] ?? 'kickerscup_');
        });

        // File Driver
        $this->singleton(FileCacheDriver::class, function () {
            $config = $this->loadAndValidateConfig('cache');
            $cachePath = $this->basePath($config['stores']['file']['path'] ?? 'storage/cache/data');
            return new FileCacheDriver($cachePath);
        });

        // Redis Driver (optional)
        $this->singleton(RedisCacheDriver::class, function () {
            $config = $this->loadAndValidateConfig('cache');
            $redisConfig = $config['stores']['redis'] ?? [];
            return new RedisCacheDriver($redisConfig);
        });
    }

    /**
     * Registriert den CacheManager als Default-Cache
     */
    private function registerCacheManager(): void
    {
        $this->singleton(CacheManager::class, function () {
            $config = $this->loadAndValidateConfig('cache');
            $cachePath = $this->basePath($config['stores']['file']['path'] ?? 'storage/cache/data');

            return CacheManager::createOptimal($cachePath);
        });

        // Alias für einfachen Zugriff
        $this->singleton('cache', function () {
            return $this->container->get(CacheManager::class);
        });
    }

    /**
     * Validiert Cache-Verzeichnisse
     */
    private function validateCacheDirectories(): void
    {
        $config = $this->loadAndValidateConfig('cache');

        $cachePath = $this->basePath($config['stores']['file']['path'] ?? 'storage/cache/data');

        if (!is_dir($cachePath)) {
            if (!mkdir($cachePath, 0755, true)) {
                throw new \RuntimeException("Cannot create cache directory: {$cachePath}");
            }
        }
    }
}
