<?php
declare(strict_types=1);

namespace Framework\Cache;

/**
 * CacheDriverInterface - Einheitliches Interface für alle Cache-Driver
 */
interface CacheDriverInterface
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value, int $ttl = 3600): bool;

    public function forget(string $key): bool;

    public function flush(): bool;

    public function exists(string $key): bool;
}