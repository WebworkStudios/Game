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

        // Script-Filter registrieren - KORRIGIERT: register() statt addFilter()
        $filterManager->register('js_script', [$jsFilters, 'jsScript']);
        $filterManager->register('js_module', [$jsFilters, 'jsModule']);
        $filterManager->register('js_inline', [$jsFilters, 'jsInline']);
        $filterManager->register('js_url', [$jsFilters, 'jsUrl']);
        $filterManager->register('js_tag', [$jsFilters, 'jsTag']);

        // Alias für bessere Lesbarkeit - KORRIGIERT: register() statt addFilter()
        $filterManager->register('script', [$jsFilters, 'jsScript']);
        $filterManager->register('module', [$jsFilters, 'jsModule']);
    }
}