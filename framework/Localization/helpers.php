<?php
if (!function_exists('__')) {
    function __(string $key, array $params = []): string
    {
        static $localization = null;

        if ($localization === null) {
            $localization = \Framework\Core\Container::getInstance()->get('localization');
        }

        return $localization->get($key, $params);
    }
}

if (!function_exists('trans')) {
    function trans(string $key, array $params = []): string
    {
        return __($key, $params);
    }
}

if (!function_exists('setLocale')) {
    function setLocale(string $locale): void
    {
        static $localization = null;

        if ($localization === null) {
            $localization = \Framework\Core\Container::getInstance()->get('localization');
        }

        $localization->currentLocale = $locale;
    }
}