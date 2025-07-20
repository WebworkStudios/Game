<?php

declare(strict_types=1);

namespace Framework\Cache;
use Framework\Cache\Drivers\ApcuCacheDriver;
use Framework\Cache\Drivers\FileCacheDriver;

/**
* CacheManager - Intelligenter Cache-Manager mit Fallback-Chain
*/
class CacheManager implements CacheDriverInterface
{
    private CacheDriverInterface $primary;
    private ?CacheDriverInterface $fallback;

    public function __construct(
        CacheDriverInterface $primary,
        ?CacheDriverInterface $fallback = null
    ) {
        $this->primary = $primary;
        $this->fallback = $fallback;
    }

    public function get(string $key): mixed
    {
        // Try primary cache first
        $value = $this->primary->get($key);
        if ($value !== null) {
            return $value;
        }

        // Try fallback cache
        if ($this->fallback) {
            $value = $this->fallback->get($key);
            if ($value !== null) {
                // Warm up primary cache
                $this->primary->put($key, $value);
                return $value;
            }
        }

        return null;
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $primarySuccess = $this->primary->put($key, $value, $ttl);
        $fallbackSuccess = $this->fallback?->put($key, $value, $ttl) ?? true;

        return $primarySuccess || $fallbackSuccess;
    }

    public function forget(string $key): bool
    {
        $primarySuccess = $this->primary->forget($key);
        $fallbackSuccess = $this->fallback?->forget($key) ?? true;

        return $primarySuccess && $fallbackSuccess;
    }

    public function flush(): bool
    {
        $primarySuccess = $this->primary->flush();
        $fallbackSuccess = $this->fallback?->flush() ?? true;

        return $primarySuccess && $fallbackSuccess;
    }

    public function exists(string $key): bool
    {
        return $this->primary->exists($key) || ($this->fallback?->exists($key) ?? false);
    }

    /**
     * Factory Method - Erstellt optimalen Cache-Manager
     */
    public static function createOptimal(string $cacheDir): self
    {
        $fileCache = new FileCacheDriver($cacheDir);

        if (new ApcuCacheDriver()->isAvailable()) {
            return new self(
                primary: new ApcuCacheDriver(),
                fallback: $fileCache
            );
        }

        return new self(primary: $fileCache);
    }
}