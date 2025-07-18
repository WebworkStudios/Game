<?php

namespace Framework\Templating\Filters;

use Framework\Assets\JavaScriptAssetManager;

/**
 * JavaScript Filter Registration für FilterManager
 */
class JavaScriptFilterRegistrar
{
    /**
     * Registriert alle JavaScript-Filter im FilterManager
     */
    public static function register(
        \Framework\Templating\FilterManager $filterManager,
        JavaScriptAssetManager              $assetManager
    ): void
    {
        $jsFilters = new JavaScriptFilters($assetManager);

        // Script-Filter registrieren
        $filterManager->addFilter('js_script', [$jsFilters, 'jsScript']);
        $filterManager->addFilter('js_module', [$jsFilters, 'jsModule']);
        $filterManager->addFilter('js_inline', [$jsFilters, 'jsInline']);
        $filterManager->addFilter('js_url', [$jsFilters, 'jsUrl']);
        $filterManager->addFilter('js_tag', [$jsFilters, 'jsTag']);

        // Alias für bessere Lesbarkeit
        $filterManager->addFilter('script', [$jsFilters, 'jsScript']);
        $filterManager->addFilter('module', [$jsFilters, 'jsModule']);
    }
}