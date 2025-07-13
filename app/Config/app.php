<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Application Configuration
    |--------------------------------------------------------------------------
    */

    'name' => 'Kickerscup.de',
    'version' => '2.0.0',
    'debug' => true, // Set to false in production
    'timezone' => 'UTC',
    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */

    'url' => 'http://localhost:8000',

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    'log' => [
        'level' => 'debug', // debug, info, warning, error
        'path' => 'storage/logs/app.log',
        'max_files' => 7, // Keep 7 days of logs
    ],
];