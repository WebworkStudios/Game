<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

use Framework\Assets\JavaScriptAssetManager;
use InvalidArgumentException;

/**
 * JavaScript Asset Filters für Template Engine
 *
 * KORRIGIERT: Bessere Null-Handling und Debugging
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
     * KORRIGIERT: Robuste Validierung und bessere Fehlermeldungen
     */
    public function jsScript(mixed $filename, string $loadType = 'defer'): string
    {
        // DEBUG: Log what we actually received
        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("jsScript called with: " . var_export($filename, true) . " (type: " . gettype($filename) . ")");
        }

        // KORRIGIERT: Handle null and empty values gracefully
        if ($filename === null) {
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("jsScript: Received null filename - skipping script registration");
            }
            return ''; // Graceful degradation
        }

        // Convert to string if possible
        if (!is_string($filename)) {
            if (is_scalar($filename)) {
                $filename = (string) $filename;
            } else {
                throw new InvalidArgumentException(
                    "jsScript(): \$filename must be a string or convertible to string, " .
                    gettype($filename) . " given"
                );
            }
        }

        // Validate string is not empty
        if (trim($filename) === '') {
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("jsScript: Empty filename provided - skipping script registration");
            }
            return ''; // Graceful degradation
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
     * KORRIGIERT: Robuste Validierung
     */
    public function jsModule(mixed $filename): string
    {
        // DEBUG: Log what we actually received
        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("jsModule called with: " . var_export($filename, true) . " (type: " . gettype($filename) . ")");
        }

        // Handle null gracefully
        if ($filename === null) {
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("jsModule: Received null filename - skipping module registration");
            }
            return '';
        }

        // Convert to string if possible
        if (!is_string($filename)) {
            if (is_scalar($filename)) {
                $filename = (string) $filename;
            } else {
                throw new InvalidArgumentException(
                    "jsModule(): \$filename must be a string or convertible to string, " .
                    gettype($filename) . " given"
                );
            }
        }

        if (trim($filename) === '') {
            return '';
        }

        $this->assetManager->addModule($filename);
        return '';
    }

    /**
     * Filter: Inline JavaScript
     *
     * KORRIGIERT: Robuste Validierung
     */
    public function jsInline(mixed $content): string
    {
        if ($content === null) {
            return '';
        }

        if (!is_string($content)) {
            if (is_scalar($content)) {
                $content = (string) $content;
            } else {
                throw new InvalidArgumentException(
                    "jsInline(): \$content must be a string or convertible to string, " .
                    gettype($content) . " given"
                );
            }
        }

        if (trim($content) === '') {
            return '';
        }

        $this->assetManager->addInlineScript($content);
        return '';
    }

    /**
     * Filter: Script-URL generieren (ohne Asset Manager)
     *
     * KORRIGIERT: Robuste Validierung
     */
    public function jsUrl(mixed $filename): string
    {
        if ($filename === null || (is_string($filename) && trim($filename) === '')) {
            return '';
        }

        if (!is_string($filename)) {
            if (is_scalar($filename)) {
                $filename = (string) $filename;
            } else {
                throw new InvalidArgumentException(
                    "jsUrl(): \$filename must be a string or convertible to string, " .
                    gettype($filename) . " given"
                );
            }
        }

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
     * KORRIGIERT: Robuste Validierung
     */
    public function jsTag(mixed $filename, string $attributes = 'defer'): string
    {
        if ($filename === null || (is_string($filename) && trim($filename) === '')) {
            return '';
        }

        if (!is_string($filename)) {
            if (is_scalar($filename)) {
                $filename = (string) $filename;
            } else {
                return ''; // Graceful degradation for complex types
            }
        }

        $url = $this->jsUrl($filename);
        if (empty($url)) {
            return '';
        }

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