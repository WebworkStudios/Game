<?php

declare(strict_types=1);

namespace Framework\Assets;

/**
 * JavaScriptAssetManager - JavaScript Integration für Template Engine
 *
 * Features:
 * - Multiple Scripts pro Template
 * - Automatische Versionierung (Cache-Busting)
 * - Script-Attribute (defer, type="module")
 * - Dev-Mode Fallback für fehlende Dateien
 * - Performance-optimierte Ausgabe
 * - File-Existenz Caching für bessere Performance
 */
class JavaScriptAssetManager
{
    private array $scripts = [];
    private string $publicPath;
    public bool $debugMode;
    private string $baseUrl;

    // NEUE Performance-Optimierung: File-Cache
    private array $fileCache = [];

    public function __construct(
        string $publicPath = 'public/js/',
        string $baseUrl = '/js/',
        bool   $debugMode = true
    )
    {
        $this->publicPath = rtrim($publicPath, '/') . '/';
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->debugMode = $debugMode;
    }

    /**
     * JavaScript-Datei registrieren
     */
    public function addScript(
        string $filename,
        array  $attributes = ['defer' => true],
        int    $priority = 100
    ): self
    {
        $this->scripts[] = [
            'filename' => $filename,
            'attributes' => $attributes,
            'priority' => $priority,
            'added_at' => microtime(true)
        ];

        return $this;
    }

    /**
     * Module-Script hinzufügen (ES6 Modules)
     */
    public function addModule(
        string $filename,
        int    $priority = 100
    ): self
    {
        return $this->addScript($filename, [
            'type' => 'module',
            'defer' => true
        ], $priority);
    }

    /**
     * Inline-Script hinzufügen (für kritische Scripts)
     */
    public function addInlineScript(
        string $content,
        int    $priority = 50
    ): self
    {
        $this->scripts[] = [
            'inline_content' => $content,
            'priority' => $priority,
            'added_at' => microtime(true)
        ];

        return $this;
    }

    /**
     * Alle Scripts als HTML-String rendern
     */
    public function render(bool $withVersioning = true): string
    {
        if (empty($this->scripts)) {
            return '';
        }

        // Scripts nach Priorität sortieren
        usort($this->scripts, fn($a, $b) => $a['priority'] <=> $b['priority']);

        $output = [];
        $output[] = '<!-- JavaScript Assets -->';

        foreach ($this->scripts as $script) {
            if (isset($script['inline_content'])) {
                $output[] = $this->renderInlineScript($script['inline_content']);
            } else {
                $output[] = $this->renderScriptTag($script, $withVersioning);
            }
        }

        return implode("\n", $output);
    }

    /**
     * Script-Tag für externe Datei rendern
     */
    private function renderScriptTag(array $script, bool $withVersioning): string
    {
        $filename = $script['filename'];
        $attributes = $script['attributes'] ?? [];

        // Datei-Überprüfung mit Caching
        if (!$this->fileExists($filename)) {
            if ($this->debugMode) {
                return "<!-- ERROR: JavaScript file '{$filename}' not found in {$this->publicPath} -->";
            }
            return '';
        }

        // URL mit Versionierung erstellen
        $url = $this->buildScriptUrl($filename, $withVersioning);

        // Attribute zusammenbauen
        $attrString = $this->buildAttributeString($attributes);

        return "<script src=\"{$url}\"{$attrString}></script>";
    }

    /**
     * Inline-Script rendern
     */
    private function renderInlineScript(string $content): string
    {
        return "<script>\n{$content}\n</script>";
    }

    /**
     * Script-URL mit optionaler Versionierung erstellen
     */
    private function buildScriptUrl(string $filename, bool $withVersioning): string
    {
        $url = $this->baseUrl . $filename;

        if ($withVersioning) {
            $filePath = $this->publicPath . $filename;
            if (file_exists($filePath)) {
                $version = filemtime($filePath);
                $url .= '?v=' . $version;
            }
        }

        return $url;
    }

    /**
     * HTML-Attribute-String erstellen
     */
    private function buildAttributeString(array $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }

        $attrs = [];
        foreach ($attributes as $key => $value) {
            if ($value === true) {
                $attrs[] = $key;
            } elseif ($value !== false && $value !== null) {
                $attrs[] = $key . '="' . htmlspecialchars((string)$value, ENT_QUOTES) . '"';
            }
        }

        return empty($attrs) ? '' : ' ' . implode(' ', $attrs);
    }

    /**
     * OPTIMIERT: Überprüfen ob JavaScript-Datei existiert (mit Caching)
     *
     * Performance-Verbesserung: Caching für wiederholte file_exists() Aufrufe
     * Besonders wichtig bei mehrfachen Renderzyklen oder komplexen Templates
     */
    private function fileExists(string $filename): bool
    {
        // Cache-Hit: Rückgabe ohne file_exists() Call
        if (isset($this->fileCache[$filename])) {
            return $this->fileCache[$filename];
        }

        // Cache-Miss: Prüfen und cachen
        $fullPath = $this->publicPath . $filename;
        $exists = file_exists($fullPath);

        // Ergebnis cachen für weitere Aufrufe
        $this->fileCache[$filename] = $exists;

        return $exists;
    }

    /**
     * Cache für File-Existenz leeren (z.B. bei Development-Builds)
     */
    public function clearFileCache(): self
    {
        $this->fileCache = [];
        return $this;
    }

    /**
     * Alle registrierten Scripts löschen
     */
    public function clear(): self
    {
        $this->scripts = [];
        return $this;
    }

    /**
     * Informationen über registrierte Scripts abrufen
     */
    public function getScripts(): array
    {
        return $this->scripts;
    }

    /**
     * Quick Helper - Multiple Scripts in einem Aufruf hinzufügen
     */
    public function addScripts(array $scripts): self
    {
        foreach ($scripts as $script) {
            if (is_string($script)) {
                $this->addScript($script);
            } elseif (is_array($script) && isset($script['filename'])) {
                $this->addScript(
                    $script['filename'],
                    $script['attributes'] ?? ['defer' => true],
                    $script['priority'] ?? 100
                );
            }
        }

        return $this;
    }

    /**
     * Conditional Loading - Script nur unter bestimmten Bedingungen laden
     */
    public function addConditionalScript(
        string   $filename,
        callable $condition,
        array    $attributes = ['defer' => true],
        int      $priority = 100
    ): self
    {
        if ($condition()) {
            $this->addScript($filename, $attributes, $priority);
        }

        return $this;
    }

}