<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Template Paths
    |--------------------------------------------------------------------------
    | Verzeichnisse in denen Templates gesucht werden
    */
    'paths' => [
        'app/Views',
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'path' => 'storage/cache/views',
        'auto_reload' => true, // Reload if template changed (dev mode)
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Engine Options
    |--------------------------------------------------------------------------
    */
    'options' => [
        'auto_escape' => true,        // XSS Protection
        'strict_variables' => false,  // Throw error on undefined variables
        'debug' => FALSE,             // Debug mode
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Filters
    |--------------------------------------------------------------------------
    */
    'filters' => [
        'custom_filter_classes' => [
            // 'custom_filter' => App\Filters\CustomFilterClass::class,
        ],
    ],
];