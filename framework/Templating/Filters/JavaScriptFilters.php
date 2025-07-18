<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

use Framework\Assets\JavaScriptAssetManager;
use InvalidArgumentException;

/**
 * JavaScript Asset Filters für Template Engine
 *
 * BEREINIGT: Debug-Logging komplett entfernt
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
     */
    public function jsScript(mixed $filename, string $loadType = 'defer'): string
    {
        // Handle null and empty values gracefully
        if ($filename === null) {
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
     */
    public function jsModule(mixed $filename): string
    {
        // Handle null gracefully
        if ($filename === null) {
            return ''; // Graceful degradation
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

        // Validate string is not empty
        if (trim($filename) === '') {
            return ''; // Graceful degradation
        }

        $this->assetManager->addModule($filename);
        return '';
    }

    /**
     * Filter: Script-URL generieren
     */
    public function scriptUrl(mixed $filename): string
    {
        // Handle null gracefully
        if ($filename === null) {
            return '';
        }

        // Convert to string if possible
        if (!is_string($filename)) {
            if (is_scalar($filename)) {
                $filename = (string) $filename;
            } else {
                return ''; // Graceful fallback
            }
        }

        // Validate string is not empty
        if (trim($filename) === '') {
            return '';
        }

        return $this->generateScriptUrl($filename);
    }

    /**
     * Sichere Script-URL Generierung
     */
    private function generateScriptUrl(string $file): string
    {
        $fullPath = 'public/js/' . $file;

        // Sichere Überprüfung der Datei-Existenz
        if (file_exists($fullPath)) {
            $version = filemtime($fullPath);
            return "/js/{$file}?v={$version}";
        }

        // Fallback ohne Versionierung
        return "/js/{$file}";
    }
}