<?php

declare(strict_types=1);

namespace Framework\Cache\Drivers;

use Framework\Cache\CacheDriverInterface;

/**
 * RedisCacheDriver - Redis Implementation (if not exists)
 */
class RedisCacheDriver implements CacheDriverInterface
{
    private ?\Redis $redis = null;

    public function __construct(private readonly array $config = [])
    {
        $this->connect();
    }

    private function connect(): void
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension not loaded');
        }

        $this->redis = new \Redis();

        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 6379;
        $timeout = $this->config['timeout'] ?? 1.0;

        if (!$this->redis->connect($host, $port, $timeout)) {
            throw new \RuntimeException("Cannot connect to Redis server {$host}:{$port}");
        }

        if (isset($this->config['password'])) {
            $this->redis->auth($this->config['password']);
        }

        if (isset($this->config['database'])) {
            $this->redis->select($this->config['database']);
        }
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);
        return $value === false ? null : unserialize($value);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->redis->setex($key, $ttl, serialize($value));
    }

    public function forget(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function flush(): bool
    {
        return $this->redis->flushDb();
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function __destruct()
    {
        if ($this->redis) {
            $this->redis->close();
        }
    }
}
