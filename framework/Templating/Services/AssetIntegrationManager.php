<?php

declare(strict_types=1);

namespace Framework\Templating\Services;

use Framework\Assets\JavaScriptAssetManager;

/**
 * AssetIntegrationManager - SRP: Verantwortlich NUR für Asset-Integration
 *
 * Früher: Teil des ViewRenderer (SRP-Verletzung)
 * Jetzt: Spezialisierte Asset-Handling-Klasse
 */
readonly class AssetIntegrationManager
{
    public function __construct(
        private JavaScriptAssetManager $assetManager = new JavaScriptAssetManager()
    ) {}

    /**
     * Injiziert Asset-Helpers in Template-Daten
     */
    public function injectAssetHelpers(array $data): array
    {
        // Asset Manager direkt verfügbar machen
        $data['js'] = $this->assetManager;

        // Helper-Funktionen für Templates
        $data['asset_helpers'] = [
            'js_script' => fn(string $file, array $attrs = ['defer' => true]) =>
            $this->assetManager->addScript($file, $attrs),
            'js_module' => fn(string $file, int $priority = 100) =>
            $this->assetManager->addModule($file, $priority),
            'js_inline' => fn(string $content, int $priority = 50) =>
            $this->assetManager->addInlineScript($content, $priority),
        ];

        // JavaScript Helper-Funktionen
        $data['js_helpers'] = [
            'add_script' => fn(string $file) => $this->assetManager->addScript($file),
            'add_module' => fn(string $file) => $this->assetManager->addModule($file),
            'script_url' => fn(string $file) => $this->generateScriptUrl($file),
        ];

        return $data;
    }

    /**
     * Injiziert JavaScript-Assets in HTML
     */
    public function injectJavaScriptAssets(string $html): string
    {
        $scripts = $this->assetManager->render();

        if (empty($scripts)) {
            return $html;
        }

        // Script-Tags vor schließendem </body> einfügen
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace(
                '/(<\/body\s*>)/i',
                "\n{$scripts}\n$1",
                $html,
                1
            );
        } else {
            // Fallback: Am Ende anhängen
            $html .= "\n{$scripts}";
        }

        // Asset Manager für nächste Anfrage zurücksetzen
        if (method_exists($this->assetManager, 'clear')) {
            $this->assetManager->clear();
        }

        return $html;
    }

    /**
     * Script-URL Generierung mit Versionierung
     */
    private function generateScriptUrl(string $file): string
    {
        $fullPath = 'public/js/' . $file;

        if (file_exists($fullPath)) {
            $version = filemtime($fullPath);
            return "/js/{$file}?v={$version}";
        }

        return "/js/{$file}";
    }

    /**
     * Asset Manager Zugriff für externe Services
     */
    public function getAssetManager(): JavaScriptAssetManager
    {
        return $this->assetManager;
    }
}