<?php
// app/Config/templating.php - FINALE OPTIMIERTE VERSION

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Template Paths
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'app/Views',
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Cache - STABIL KONFIGURIERT
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,  // Vorerst deaktiviert für Stabilität
        'path' => 'storage/cache/views',
        'auto_reload' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Engine Options
    |--------------------------------------------------------------------------
    */
    'options' => [
        'auto_escape' => true,        // XSS Protection
        'strict_variables' => false,  // Flexible Variable Handling
        'debug' => true,              // Debug Informationen
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Filters
    |--------------------------------------------------------------------------
    */
    'filters' => [
        'custom_filter_classes' => [
            // Hier können später eigene Filter-Klassen hinzugefügt werden
        ],
    ],
];