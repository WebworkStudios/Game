<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default MySQL Connection
    |--------------------------------------------------------------------------
    | Vereinfachte Konfiguration - ConnectionManager adaptiert automatisch
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
    | Read Replicas (optional)
    |--------------------------------------------------------------------------
    | Mehrere Verbindungen für Read/Write-Splitting:
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

    /*
    |--------------------------------------------------------------------------
    | Additional Named Connections
    |--------------------------------------------------------------------------
    | Weitere benannte Verbindungen für verschiedene Zwecke:
    */
    /*
    'analytics' => [
        'host' => 'analytics.localhost',
        'port' => 3306,
        'database' => 'kickerscup_analytics',
        'username' => 'analytics',
        'password' => 'analytics_password',
        'charset' => 'utf8mb4',
        'type' => 'read',
        'weight' => 1,
    ],

    'reporting' => [
        'host' => 'reporting.localhost',
        'port' => 3306,
        'database' => 'kickerscup_reports',
        'username' => 'reporting',
        'password' => 'reporting_password',
        'charset' => 'utf8mb4',
        'type' => 'read',
        'weight' => 1,
    ],
    */
];