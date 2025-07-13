<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Localization Configuration
    |--------------------------------------------------------------------------
    */

    'default_locale' => 'de',
    'fallback_locale' => 'de',

    'supported_locales' => [
        'de' => 'Deutsch',
        'en' => 'English',
        'fr' => 'Français',
        'es' => 'Español',
    ],

    /*
    |--------------------------------------------------------------------------
    | Language Files Path
    |--------------------------------------------------------------------------
    */

    'languages_path' => 'app/Languages',

    /*
    |--------------------------------------------------------------------------
    | Detection Configuration
    |--------------------------------------------------------------------------
    */

    'detection' => [
        'session_key' => 'locale',
        'cookie_name' => 'app_locale',
        'cookie_lifetime' => 60 * 60 * 24 * 365, // 1 year
        'url_parameter' => 'lang', // ?lang=en
    ],
];