<?php

declare(strict_types=1);

namespace Framework\Core;

/**
 * CacheDebugInfo - Zeigt aktiven Cache-Modus und Performance-Statistiken
 */
class CacheDebugInfo
{
    /**
     * Zeigt detaillierte Informationen über aktiven Cache-Driver
     */
    public static function getCurrentCacheStatus(): array
    {
        $driver = CacheDriverDetector::detectOptimalDriver();

        return [
            'active_driver' => $driver,
            'available_drivers' => self::getAvailableDrivers(),
            'driver_details' => self::getDriverDetails($driver),
            'performance_info' => self::getPerformanceInfo($driver),
            'memory_usage' => self::getMemoryUsage($driver),
            'recommendations' => self::getRecommendations($driver),
        ];
    }

    /**
     * Einfache Status-Anzeige für Development
     */
    public static function getSimpleStatus(): string
    {
        $driver = CacheDriverDetector::detectOptimalDriver();
        $emoji = self::getDriverEmoji($driver);

        return "Cache: {$emoji} {$driver}";
    }

    /**
     * Prüft alle verfügbaren Cache-Driver
     */
    private static function getAvailableDrivers(): array
    {
        return [
            'apcu' => [
                'available' => function_exists('apcu_fetch') && apcu_enabled(),
                'status' => self::getApcuStatus(),
            ],
            'redis' => [
                'available' => self::checkRedis(),
                'status' => self::getRedisStatus(),
            ],
            'memcached' => [
                'available' => self::checkMemcached(),
                'status' => self::getMemcachedStatus(),
            ],
            'file' => [
                'available' => true,
                'status' => 'Always available',
            ],
        ];
    }

    /**
     * Detaillierte Informationen über aktiven Driver
     */
    private static function getDriverDetails(string $driver): array
    {
        return match($driver) {
            'apcu' => [
                'type' => 'Shared Memory Cache',
                'scope' => 'Single Server',
                'persistence' => 'Process Restart clears cache',
                'ideal_for' => 'Shared Hosting, Single Server, Ultra-fast access',
                'memory_info' => self::getApcuMemoryInfo(),
            ],

            'redis' => [
                'type' => 'In-Memory Database',
                'scope' => 'Multi-Server capable',
                'persistence' => 'Configurable (Memory/Disk)',
                'ideal_for' => 'Production, Multi-Server, Clustering',
                'connection_info' => self::getRedisConnectionInfo(),
            ],

            'memcached' => [
                'type' => 'Distributed Memory Cache',
                'scope' => 'Multi-Server',
                'persistence' => 'Memory only',
                'ideal_for' => 'Large-scale distributed systems',
                'server_info' => self::getMemcachedServerInfo(),
            ],

            'file' => [
                'type' => 'File System Cache',
                'scope' => 'Local Server',
                'persistence' => 'Disk-based, survives restarts',
                'ideal_for' => 'Universal fallback, Development',
                'storage_info' => self::getFileStorageInfo(),
            ],
        };
    }

    /**
     * Performance-Informationen für aktuellen Driver
     */
    private static function getPerformanceInfo(string $driver): array
    {
        $benchmarks = [
            'apcu' => [
                'read_speed' => '~0.01ms',
                'write_speed' => '~0.02ms',
                'relative_speed' => '500x faster than file',
                'concurrent_access' => 'Excellent',
            ],
            'redis' => [
                'read_speed' => '~0.1ms',
                'write_speed' => '~0.15ms',
                'relative_speed' => '50x faster than file',
                'concurrent_access' => 'Excellent + Network overhead',
            ],
            'memcached' => [
                'read_speed' => '~0.2ms',
                'write_speed' => '~0.25ms',
                'relative_speed' => '25x faster than file',
                'concurrent_access' => 'Very good + Network overhead',
            ],
            'file' => [
                'read_speed' => '~2-5ms',
                'write_speed' => '~3-8ms',
                'relative_speed' => 'Baseline',
                'concurrent_access' => 'Good (file locking)',
            ],
        ];

        return $benchmarks[$driver] ?? [];
    }

    /**
     * Memory-Usage Informationen
     */
    private static function getMemoryUsage(string $driver): array
    {
        return match($driver) {
            'apcu' => self::getApcuMemoryInfo(),
            'redis' => ['info' => 'Connect to Redis for memory stats'],
            'memcached' => ['info' => 'Connect to Memcached for memory stats'],
            'file' => self::getFileStorageInfo(),
        };
    }

    /**
     * Empfehlungen basierend auf aktuellem Setup
     */
    private static function getRecommendations(string $driver): array
    {
        $recommendations = [];

        if ($driver === 'file') {
            $recommendations[] = '💡 Install APCu for 500x faster caching on shared hosting';
            $recommendations[] = '🚀 Consider Redis for production/multi-server setups';
            $recommendations[] = '⚠️  File cache is slowest option - upgrade recommended';
        }

        if ($driver === 'apcu') {
            $apcuInfo = apcu_cache_info();
            $memUsage = $apcuInfo['memory_usage'] ?? 0;
            $memAvailable = $apcuInfo['memory_available'] ?? 0;

            if ($memAvailable > 0 && ($memUsage / ($memUsage + $memAvailable)) > 0.8) {
                $recommendations[] = '⚠️  APCu memory usage high (>80%) - consider increasing apc.shm_size';
            }

            $recommendations[] = '✅ Excellent choice for single-server gaming applications';
            $recommendations[] = '💡 For multi-server: consider Redis for shared cache';
        }

        if ($driver === 'redis') {
            $recommendations[] = '✅ Excellent choice for production gaming applications';
            $recommendations[] = '🎮 Perfect for live match updates and leaderboards';
            $recommendations[] = '💡 Consider Redis clustering for high-traffic gaming';
        }

        return $recommendations;
    }

    /**
     * Helper Methods für spezifische Driver-Checks
     */
    private static function getApcuStatus(): string
    {
        if (!function_exists('apcu_fetch')) {
            return 'Extension not installed';
        }
        if (!apcu_enabled()) {
            return 'Extension installed but disabled';
        }
        return 'Available and enabled';
    }

    private static function getApcuMemoryInfo(): array
    {
        if (!function_exists('apcu_cache_info')) {
            return ['error' => 'APCu not available'];
        }

        $info = apcu_cache_info();
        $memUsage = $info['memory_usage'] ?? 0;
        $memAvailable = $info['memory_available'] ?? 0;
        $total = $memUsage + $memAvailable;

        return [
            'total_memory' => $total > 0 ? round($total / 1024 / 1024, 2) . ' MB' : 'Unknown',
            'used_memory' => round($memUsage / 1024 / 1024, 2) . ' MB',
            'available_memory' => round($memAvailable / 1024 / 1024, 2) . ' MB',
            'usage_percentage' => $total > 0 ? round(($memUsage / $total) * 100, 1) . '%' : 'Unknown',
            'cache_hits' => $info['cache_hits'] ?? 0,
            'cache_misses' => $info['cache_misses'] ?? 0,
        ];
    }

    private static function checkRedis(): bool
    {
        return CacheDriverDetector::isRedisAvailable();
    }

    private static function getRedisStatus(): string
    {
        if (!extension_loaded('redis')) {
            return 'Extension not installed';
        }
        if (!class_exists('Redis')) {
            return 'Class not available';
        }

        try {
            $redis = new \Redis();
            if ($redis->connect('127.0.0.1', 6379, 1)) {
                $redis->close();
                return 'Available and connected';
            }
            return 'Extension available but connection failed';
        } catch (\Throwable) {
            return 'Connection failed';
        }
    }

    private static function getRedisConnectionInfo(): array
    {
        try {
            $redis = new \Redis();
            if ($redis->connect('127.0.0.1', 6379, 1)) {
                $info = $redis->info();
                $redis->close();

                return [
                    'version' => $info['redis_version'] ?? 'Unknown',
                    'used_memory' => $info['used_memory_human'] ?? 'Unknown',
                    'connected_clients' => $info['connected_clients'] ?? 'Unknown',
                    'uptime' => isset($info['uptime_in_seconds']) ?
                        round($info['uptime_in_seconds'] / 3600, 1) . ' hours' : 'Unknown',
                ];
            }
        } catch (\Throwable) {}

        return ['error' => 'Could not retrieve Redis info'];
    }

    private static function checkMemcached(): bool
    {
        return CacheDriverDetector::isMemcachedAvailable();
    }

    private static function getMemcachedStatus(): string
    {
        if (!extension_loaded('memcached')) {
            return 'Extension not installed';
        }
        if (!class_exists('Memcached')) {
            return 'Class not available';
        }

        try {
            $memcached = new \Memcached();
            $memcached->addServer('127.0.0.1', 11211);
            $stats = $memcached->getStats();
            return !empty($stats) ? 'Available and connected' : 'Connection failed';
        } catch (\Throwable) {
            return 'Connection failed';
        }
    }

    private static function getMemcachedServerInfo(): array
    {
        try {
            $memcached = new \Memcached();
            $memcached->addServer('127.0.0.1', 11211);
            $stats = $memcached->getStats();

            if (!empty($stats)) {
                $serverStats = array_values($stats)[0];
                return [
                    'version' => $serverStats['version'] ?? 'Unknown',
                    'uptime' => isset($serverStats['uptime']) ?
                        round($serverStats['uptime'] / 3600, 1) . ' hours' : 'Unknown',
                    'bytes_used' => isset($serverStats['bytes']) ?
                        round($serverStats['bytes'] / 1024 / 1024, 2) . ' MB' : 'Unknown',
                ];
            }
        } catch (\Throwable) {}

        return ['error' => 'Could not retrieve Memcached info'];
    }

    private static function getFileStorageInfo(): array
    {
        $cacheDir = 'storage/cache';
        if (!is_dir($cacheDir)) {
            return ['error' => 'Cache directory does not exist'];
        }

        $size = self::getDirectorySize($cacheDir);
        $files = self::countCacheFiles($cacheDir);

        return [
            'cache_directory' => $cacheDir,
            'total_size' => round($size / 1024 / 1024, 2) . ' MB',
            'total_files' => $files,
            'writable' => is_writable($cacheDir) ? 'Yes' : 'No',
        ];
    }

    private static function getDirectorySize(string $dir): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private static function countCacheFiles(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $count++;
            }
        }

        return $count;
    }

    private static function getDriverEmoji(string $driver): string
    {
        return match($driver) {
            'apcu' => '🚀',
            'redis' => '⚡',
            'memcached' => '💾',
            'file' => '📁',
            default => '❓'
        };
    }
}