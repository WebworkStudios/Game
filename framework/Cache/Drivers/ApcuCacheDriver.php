<?php
declare(strict_types=1);

namespace Framework\Cache\Drivers;

use Framework\Cache\CacheDriverInterface;

/**
 * ApcuCacheDriver - APCu Implementation
 */
readonly class ApcuCacheDriver implements CacheDriverInterface
{
    public function __construct(
        private string $prefix = 'kickerscup_'
    )
    {
    }

    public function get(string $key): mixed
    {
        $success = false;
        $value = apcu_fetch($this->prefix . $key, $success);
        return $success ? $value : null;
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return apcu_store($this->prefix . $key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return apcu_delete($this->prefix . $key);
    }

    public function flush(): bool
    {
        return apcu_clear_cache();
    }

    public function exists(string $key): bool
    {
        return apcu_exists($this->prefix . $key);
    }

    public function isAvailable(): bool
    {
        return function_exists('apcu_fetch') && apcu_enabled();
    }
}