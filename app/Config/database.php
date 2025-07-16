<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default MySQL Connection
    |--------------------------------------------------------------------------
    */
    'default' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'kickerscup',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'type' => 'write',
        'weight' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | ConnectionManager Compatibility
    |--------------------------------------------------------------------------
    | Diese Struktur wird vom DatabaseServiceProvider benötigt
    */
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'kickerscup',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Read Replicas (optional)
    |--------------------------------------------------------------------------
    | Uncomment to enable read/write splitting:
    */
    /*
    'default' => [
        [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'kickerscup',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'type' => 'write',
            'weight' => 1,
        ],
        [
            'host' => 'localhost-read',
            'port' => 3306,
            'database' => 'kickerscup',
            'username' => 'read_user',
            'password' => 'read_password',
            'charset' => 'utf8mb4',
            'type' => 'read',
            'weight' => 1,
        ],
    ],
    */
];