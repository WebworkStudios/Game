<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    */
    'default' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'kickerscup', // Change this to your database name
        'username' => 'root',
        'password' => '', // Add your database password
        'charset' => 'utf8mb4',
        'type' => 'write',
        'weight' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Database (Example for second connection)
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'driver' => 'postgresql',
        'host' => 'localhost',
        'port' => 5432,
        'database' => 'analytics',
        'username' => 'postgres',
        'password' => '',
        'type' => 'read',
        'weight' => 1,
    ],
];