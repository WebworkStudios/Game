<?php
namespace Framework\Cache\Drivers;

use Framework\Cache\CacheDriverInterface;

/**
 * Null Cache Driver für Fallback-Szenarien
 */
class NullCacheDriver implements CacheDriverInterface
{
    public function get(string $key): mixed
    {
        return null;
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        return true; // Simulate success
    }

    public function forget(string $key): bool
    {
        return true;
    }

    public function flush(): bool
    {
        return true;
    }

    public function exists(string $key): bool
    {
        return false;
    }
}
