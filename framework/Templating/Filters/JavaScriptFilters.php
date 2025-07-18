<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

use Framework\Assets\JavaScriptAssetManager;
use InvalidArgumentException;

/**
 * JavaScript Asset Filters für Template Engine
 *
 * Ermöglicht die Verwendung von JavaScript-Assets direkt in Templates:
 * {{ 'main.js'|js_script }}
 * {{ 'matchday.js'|js_module }}
 */
class JavaScriptFilters
{
    private JavaScriptAssetManager $assetManager;

    public function __construct(JavaScriptAssetManager $assetManager)
    {
        $this->assetManager = $assetManager;
    }

    /**
     * Filter: JavaScript-Datei hinzufügen
     *
     * Verwendung: {{ 'main.js'|js_script }}
     * Verwendung: {{ 'analytics.js'|js_script('async') }}
     */
    public function jsScript(string $filename, string $loadType = 'defer'): string
    {
        if (!is_string($filename) || trim($filename) === '') {
            throw new InvalidArgumentException("jsScript(): \$filename must be a non-empty string");
        }

        $attributes = match($loadType) {
            'async' => ['async' => true],
            'defer' => ['defer' => true],
            'module' => ['type' => 'module', 'defer' => true],
            'immediate' => [],
            default => ['defer' => true]
        };

        $this->assetManager->addScript($filename, $attributes);
        return '';
    }


    /**
     * Filter: ES6 Module hinzufügen
     *
     * Verwendung: {{ 'app.js'|js_module }}
     */
    public function jsModule(string $filename): string
    {
        $this->assetManager->addModule($filename);
        return '';
    }

    /**
     * Filter: Inline JavaScript
     *
     * Verwendung: {{ 'console.log("Hello");'|js_inline }}
     */
    public function jsInline(string $content): string
    {
        $this->assetManager->addInlineScript($content);
        return '';
    }

    /**
     * Filter: Script-URL generieren (ohne Asset Manager)
     *
     * Verwendung: {{ 'main.js'|js_url }}
     * Output: /js/main.js?v=1234567890
     */
    public function jsUrl(string $filename): string
    {
        $publicPath = 'public/js/' . $filename;
        $baseUrl = '/js/' . $filename;

        if (file_exists($publicPath)) {
            $version = filemtime($publicPath);
            return $baseUrl . '?v=' . $version;
        }

        return $baseUrl;
    }

    /**
     * Filter: Script-Tag direkt ausgeben (für spezielle Fälle)
     *
     * Verwendung: {{ 'main.js'|js_tag }}
     * Output: <script src="/js/main.js?v=123" defer></script>
     */
    public function jsTag(string $filename, string $attributes = 'defer'): string
    {
        $url = $this->jsUrl($filename);
        $attrs = $this->buildAttributeString($attributes);

        return "<script src=\"{$url}\"{$attrs}></script>";
    }

    /**
     * Helper: Attribute-String aus String erstellen
     */
    private function buildAttributeString(string $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }

        $attrs = [];
        $attributeList = explode(' ', $attributes);

        foreach ($attributeList as $attr) {
            $attr = trim($attr);
            if ($attr) {
                $attrs[] = $attr;
            }
        }

        return empty($attrs) ? '' : ' ' . implode(' ', $attrs);
    }
}

