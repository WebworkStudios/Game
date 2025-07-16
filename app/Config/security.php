<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */
    'session' => [
        'lifetime' => 7200, // 2 hours
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax', // Lax, Strict, None
        'gc_maxlifetime' => 7200,
        'gc_probability' => 1,
        'gc_divisor' => 100,
        'save_path' => 'storage/sessions', // ← HINZUGEFÜGT: Für SecurityServiceProvider
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */
    'csrf' => [
        'token_lifetime' => 7200, // 2 hours
        'regenerate_on_login' => true,
        'exclude_routes' => [
            '/api/*',      // API routes (use different auth)
            '/webhooks/*', // Webhook routes
        ],
    ],
];