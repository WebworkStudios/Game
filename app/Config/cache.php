<?php

declare(strict_types=1);

// app/Config/cache.php - NUR intelligente Cache-Driver Auto-Detection

use Framework\Core\CacheDriverDetector;

return [
    /*
    |--------------------------------------------------------------------------
    | Intelligente Cache-Driver Detection (Convention over Configuration)
    |--------------------------------------------------------------------------
    */

    // Automatische Driver-Selection über Static Method Call
    'default' => [CacheDriverDetector::class, 'detectOptimalDriver'],

    /*
    |--------------------------------------------------------------------------
    | Cache-Driver Konfigurationen
    |--------------------------------------------------------------------------
    */

    'stores' => [
        'apcu' => [
            'driver' => 'apcu',
            'prefix' => 'kickerscup_',
        ],

        'redis' => [
            'driver' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
        ],

        'memcached' => [
            'driver' => 'memcached',
            'servers' => [
                [
                    'host' => '127.0.0.1',
                    'port' => 11211,
                    'weight' => 100,
                ],
            ],
        ],

        'file' => [
            'driver' => 'file',
            'path' => 'storage/cache/data',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default TTL Werte
    |--------------------------------------------------------------------------
    */

    'ttl' => 3600, // Standard: 1 Stunde
];