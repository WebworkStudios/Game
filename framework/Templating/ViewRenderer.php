<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Assets\JavaScriptAssetManager;
use Framework\Core\ConfigManager;
use Framework\Http\HttpStatus;
use Framework\Http\Response;
use Framework\Localization\Translator;
use Framework\Security\Csrf;
use Framework\Templating\Filters\JavaScriptFilterRegistrar;

/**
 * ViewRenderer - Vollständig korrigierte Version
 *
 * ALLE FIXES:
 * ✅ Debug-Modus aus Config statt hardcoded
 * ✅ Verwendung der EXISTIERENDEN JavaScriptAssetManager Methoden
 * ✅ Robuste Config-Ladung mit Fallbacks
 * ✅ Korrekte JavaScript-Asset-Integration
 * ✅ Bessere Error-Handling
 */
readonly class ViewRenderer
{
    public function __construct(
        private TemplateEngine $engine,
        private ?Translator $translator = null,
        private ?Csrf $csrf = null,
        private JavaScriptAssetManager $assetManager = new JavaScriptAssetManager(),
        private array $appConfig = []
    ) {
        // JavaScript-Filter registrieren
        $this->registerJavaScriptFilters();
    }

    /**
     * Factory-Methode mit korrekter Config-Ladung
     */
    public static function create(
        TemplateEngine $engine,
        ?Translator $translator = null,
        ?Csrf $csrf = null,
        ?JavaScriptAssetManager $assetManager = null,
        ?ConfigManager $configManager = null
    ): self {
        // App-Konfiguration laden
        $appConfig = self::loadAppConfig($configManager);

        // Asset Manager mit korrekter Debug-Konfiguration
        $assetManager = $assetManager ?? new JavaScriptAssetManager(
            publicPath: 'public/js/',
            baseUrl: '/js/',
            debugMode: $appConfig['debug'] ?? false
        );

        return new self(
            engine: $engine,
            translator: $translator,
            csrf: $csrf,
            assetManager: $assetManager,
            appConfig: $appConfig
        );
    }

    /**
     * Robuste App-Config-Ladung
     */
    private static function loadAppConfig(?ConfigManager $configManager): array
    {
        if ($configManager === null) {
            // Fallback: Direktes Config-Loading
            $configPath = __DIR__ . '/../../app/Config/app.php';
            if (file_exists($configPath)) {
                try {
                    $config = require $configPath;
                    return is_array($config) ? $config : [];
                } catch (\Throwable $e) {
                    error_log("ViewRenderer: Direct config loading failed: " . $e->getMessage());
                }
            }
            return [];
        }

        try {
            return $configManager->get('app/Config/app.php');
        } catch (\Throwable $e) {
            error_log("ViewRenderer: ConfigManager loading failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Hauptmethode: Rendert Template zu String
     */
    public function render(string $template, array $data = []): string
    {
        // Asset Manager für Template verfügbar machen
        $data = $this->injectAssetManager($data);

        // Framework-Services injizieren
        $data = $this->injectFrameworkServices($data);

        // Global Template Variables injizieren
        $data = $this->injectGlobalVariables($data);

        // Template rendern
        $html = $this->engine->render($template, $data);

        // JavaScript-Assets automatisch injizieren
        $html = $this->injectJavaScriptAssets($html);

        return $html;
    }

    /**
     * Erstellt HTTP Response aus Template
     */
    public function response(
        string $template,
        array $data = [],
        HttpStatus $status = HttpStatus::OK,
        array $headers = []
    ): Response {
        try {
            $content = $this->render($template, $data);

            // Content-Type Header setzen
            $headers['Content-Type'] = 'text/html; charset=UTF-8';

            return new Response($status, $headers, $content);

        } catch (\Throwable $e) {
            return $this->handleRenderError($e, $template, $data, $status, $headers);
        }
    }

    /**
     * Asset Manager für Templates verfügbar machen
     */
    private function injectAssetManager(array $data): array
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

        return $data;
    }

    /**
     * KORRIGIERT: JavaScript-Assets mit existierenden Methoden injizieren
     */
    private function injectJavaScriptAssets(string $html): string
    {
        // ORIGINAL-Implementierung: Verwendet existierende render() Methode
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
     * JavaScript-Filter registrieren
     */
    private function registerJavaScriptFilters(): void
    {
        try {
            // Prüfen ob TemplateEngine FilterManager hat
            if (method_exists($this->engine, 'getFilterManager')) {
                $filterManager = $this->engine->getFilterManager();
                if ($filterManager !== null) {
                    JavaScriptFilterRegistrar::register($filterManager, $this->assetManager);
                }
            }
        } catch (\Throwable $e) {
            // Graceful fallback - Filter-Registrierung ist optional
            if ($this->getDebugMode()) {
                error_log("JavaScript filters could not be registered: " . $e->getMessage());
            }
        }
    }

    /**
     * FIXED: Debug-Modus aus Konfiguration ermitteln
     */
    private function getDebugMode(): bool
    {
        // 1. Aus Constructor-appConfig (bevorzugt)
        if (!empty($this->appConfig) && isset($this->appConfig['debug'])) {
            return (bool) $this->appConfig['debug'];
        }

        // 2. Fallback: Direktes Config-Loading
        $configPath = __DIR__ . '/../../app/Config/app.php';
        if (file_exists($configPath)) {
            try {

                $config = require $configPath;
                if (is_array($config) && isset($config['debug'])) {
                    return (bool) $config['debug'];
                }
            } catch (\Throwable $e) {
                error_log("ViewRenderer getDebugMode() error: " . $e->getMessage());
            }
        }

        // 3. Sicher für Production
        return false;
    }

    /**
     * Framework-Services in Template-Daten injizieren
     */
    private function injectFrameworkServices(array $data): array
    {
        // Translation Services
        if ($this->translator !== null) {
            $data = $this->injectTranslationServices($data);
        }

        // Security Services
        if ($this->csrf !== null) {
            $data = $this->injectSecurityServices($data);
        }

        // JavaScript Asset Helpers
        $data['js_helpers'] = [
            'add_script' => fn(string $file) => $this->assetManager->addScript($file),
            'add_module' => fn(string $file) => $this->assetManager->addModule($file),
            'script_url' => fn(string $file) => $this->generateScriptUrl($file),
        ];

        return $data;
    }

    /**
     * Script-URL Generierung
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
     * KORRIGIERT: Global Template Variables mit korrektem Debug-Wert
     */
    private function injectGlobalVariables(array $data): array
    {
        // App-Informationen aus Config
        $data['app_name'] = $this->appConfig['name'] ?? 'KickersCup Manager';
        $data['app_version'] = $this->appConfig['version'] ?? '2.0.0';
        $data['app_debug'] = $this->getDebugMode(); // ✅ Korrekte Debug-Ermittlung
        $data['app_locale'] = $this->appConfig['locale'] ?? 'de';

        // Asset-URLs
        $data['asset_url'] = '/assets';
        $data['js_url'] = '/js';
        $data['css_url'] = '/css';

        return $data;
    }

    /**
     * Translation Services injizieren
     */
    private function injectTranslationServices(array $data): array
    {
        try {
            if (!isset($data['current_locale'])) {
                $data['current_locale'] = $this->translator->getLocale();
            }

            if (!isset($data['available_locales'])) {
                $data['available_locales'] = $this->translator->getSupportedLocales();
            }

            // Translation Helper für Templates
            $data['trans'] = fn(string $key, array $params = []) =>
            $this->translator->translate($key, $params);

        } catch (\Throwable $e) {
            // Graceful fallback
            $data['current_locale'] = $this->appConfig['locale'] ?? 'de';
            $data['available_locales'] = ['de', 'en'];
            $data['trans'] = fn(string $key, array $params = []) => $key;
        }

        return $data;
    }

    /**
     * Security Services injizieren
     */
    private function injectSecurityServices(array $data): array
    {
        try {
            if (!isset($data['csrf_token'])) {
                $data['csrf_token'] = $this->csrf->getToken();
            }

            if (!isset($data['csrf_field'])) {
                $data['csrf_field'] = $this->csrf->getTokenField();
            }

        } catch (\Throwable $e) {
            // Graceful fallback
            $data['csrf_token'] = '';
            $data['csrf_field'] = '';
        }

        return $data;
    }

    /**
     * Render-Error Handling
     */
    private function handleRenderError(
        \Throwable $e,
        string $template,
        array $data,
        HttpStatus $status,
        array $headers
    ): Response {
        $message = $this->getDebugMode() ?
            "Template render error in '{$template}': " . $e->getMessage() :
            'Internal Server Error';

        return new Response(
            HttpStatus::INTERNAL_SERVER_ERROR,
            ['Content-Type' => 'text/html'],
            "<h1>Template Error</h1><p>{$message}</p>"
        );
    }

    /**
     * Debug-Informationen für Troubleshooting
     */
    public function getDebugInfo(): array
    {
        return [
            'debug_mode' => $this->getDebugMode(),
            'app_config_loaded' => !empty($this->appConfig),
            'app_config_debug_value' => $this->appConfig['debug'] ?? 'not set',
            'translator_available' => $this->translator !== null,
            'csrf_available' => $this->csrf !== null,
            'asset_manager_debug' => $this->assetManager->debugMode ?? false,
            'asset_manager_scripts' => count($this->assetManager->getScripts()),
            'template_engine_class' => get_class($this->engine),
        ];
    }
}