<?php


declare(strict_types=1);

namespace Framework\Core;

/**
 * CacheDriverDetector - Einfache Auto-Detection für Cache-Driver
 *
 * Implementiert nur die intelligente Driver-Selection, nichts mehr.
 */
class CacheDriverDetector
{
    /**
     * Erkennt automatisch den besten verfügbaren Cache-Driver
     */
    public static function detectOptimalDriver(): string
    {
        // 1. APCu - Optimal für Shared Hosting / Single Server
        if (self::isApcuAvailable()) {
            return 'apcu';
        }

        // 2. Redis - Optimal für Production / Multi-Server
        if (self::isRedisAvailable()) {
            return 'redis';
        }

        // 3. Memcached - Gute Alternative
        if (self::isMemcachedAvailable()) {
            return 'memcached';
        }

        // 4. File Cache - Immer verfügbarer Fallback
        return 'file';
    }

    /**
     * Prüft APCu Verfügbarkeit
     */
    private static function isApcuAvailable(): bool
    {
        return function_exists('apcu_fetch') && apcu_enabled();
    }

    /**
     * Prüft Redis Verfügbarkeit mit Connection-Test
     */
    public static function isRedisAvailable(): bool
    {
        if (!extension_loaded('redis') || !class_exists('Redis')) {
            return false;
        }

        try {
            $redis = new \Redis();

            if ($redis->connect('127.0.0.1', 6379, 1)) { // 1 second timeout
                $redis->close();
                return true;
            }
        } catch (\Throwable) {
            // Connection failed
        }

        return false;
    }

    /**
     * Prüft Memcached Verfügbarkeit
     */
    public static function isMemcachedAvailable(): bool
    {
        if (!extension_loaded('memcached') || !class_exists('Memcached')) {
            return false;
        }

        try {
            $memcached = new \Memcached();
            $memcached->addServer('127.0.0.1', 11211);

            // Quick test
            $stats = $memcached->getStats();
            return !empty($stats);
        } catch (\Throwable) {
            return false;
        }
    }
}