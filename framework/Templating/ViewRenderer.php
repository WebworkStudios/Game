<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Assets\JavaScriptAssetManager;
use Framework\Http\HttpStatus;
use Framework\Http\Response;
use Framework\Localization\Translator;
use Framework\Security\Csrf;
use Framework\Templating\Filters\JavaScriptFilterRegistrar;

/**
 * ViewRenderer - Erweitert um JavaScript Asset Management
 *
 * NEUE FEATURES:
 * - Automatische JavaScript-Asset Integration
 * - Script-Injection am Ende des HTML-Body
 * - Filter-basierte Script-Registrierung in Templates
 */
readonly class ViewRenderer
{
    private JavaScriptAssetManager $assetManager;

    public function __construct(
        private TemplateEngine $engine,
        private ?Translator    $translator = null,
        private ?Csrf          $csrf = null,
        ?JavaScriptAssetManager $assetManager = null
    ) {
        // JavaScript Asset Manager initialisieren
        $this->assetManager = $assetManager ?? new JavaScriptAssetManager(
            publicPath: 'public/js/',
            baseUrl: '/js/',
            debugMode: ($_ENV['APP_DEBUG'] ?? 'false') === 'true'
        );

        // JavaScript-Filter in der Template-Engine registrieren
        $this->registerJavaScriptFilters();
    }

    /**
     * Rendert Template zu HTTP Response mit JavaScript-Asset Integration
     */
    public function render(
        string     $template,
        array      $data = [],
        HttpStatus $status = HttpStatus::OK,
        array      $headers = []
    ): Response {
        try {
            // Asset Manager für Template verfügbar machen
            $data = $this->injectAssetManager($data);

            // Auto-inject Framework-Services
            $data = $this->injectFrameworkServices($data);

            // Auto-inject Global Template Variables
            $data = $this->injectGlobalVariables($data);

            // Template rendern
            $content = $this->engine->render($template, $data);

            // JavaScript-Assets automatisch am Ende des Body einfügen
            $content = $this->injectJavaScriptAssets($content);

            // Post-process content (CSRF meta injection, etc.)
            $content = $this->postProcessContent($content, $data);

            // Set Content-Type header
            $headers['Content-Type'] = 'text/html; charset=UTF-8';

            return new Response($status, $headers, $content);

        } catch (\Throwable $e) {
            return $this->handleRenderError($e, $template, $data, $status, $headers);
        }
    }

    /**
     * JavaScript Asset Manager in Template-Daten injizieren
     */
    private function injectAssetManager(array $data): array
    {
        // Asset Manager für Templates verfügbar machen
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
     * JavaScript-Assets automatisch in HTML einfügen
     */
    private function injectJavaScriptAssets(string $content): string
    {
        $scripts = $this->assetManager->render();

        if (empty($scripts)) {
            return $content;
        }

        // Script-Tags vor schließendem </body> einfügen
        if (stripos($content, '</body>') !== false) {
            $content = preg_replace(
                '/(<\/body\s*>)/i',
                "\n{$scripts}\n$1",
                $content,
                1
            );
        } else {
            // Fallback: Am Ende anhängen
            $content .= "\n{$scripts}";
        }

        // Asset Manager für nächste Anfrage zurücksetzen
        $this->assetManager->clear();

        return $content;
    }

    /**
     * JavaScript-Filter in der Template-Engine registrieren
     */
    private function registerJavaScriptFilters(): void
    {
        // Filter-Manager aus Engine holen (falls verfügbar)
        $reflection = new \ReflectionClass($this->engine);

        try {
            $filterManagerProperty = $reflection->getProperty('filterManager');
            $filterManagerProperty->setAccessible(true);
            $filterManager = $filterManagerProperty->getValue($this->engine);

            if ($filterManager instanceof \Framework\Templating\FilterManager) {
                JavaScriptFilterRegistrar::register($filterManager, $this->assetManager);
            }
        } catch (\ReflectionException $e) {
            // Filter-Manager nicht verfügbar - Filter können manuell registriert werden
        }
    }

    /**
     * Framework-Services in Template-Daten injizieren (erweitert)
     */
    private function injectFrameworkServices(array $data): array
    {
        // Bestehende Service-Injection
        if ($this->translator !== null) {
            $data = $this->injectTranslationServices($data);
        }

        if ($this->csrf !== null) {
            $data = $this->injectSecurityServices($data);
        }

        // JavaScript Asset Helpers
        $data['js_helpers'] = [
            'add_script' => fn(string $file) => $this->assetManager->addScript($file),
            'add_module' => fn(string $file) => $this->assetManager->addModule($file),
            'script_url' => fn(string $file) => '/js/' . $file . '?v=' . filemtime('public/js/' . $file),
        ];

        return $data;
    }

    /**
     * Global Template Variables injizieren (erweitert)
     */
    private function injectGlobalVariables(array $data): array
    {
        // App-Informationen
        $data['app_name'] = $_ENV['APP_NAME'] ?? 'KickersCup Manager';
        $data['app_version'] = $_ENV['APP_VERSION'] ?? '1.0.0';
        $data['app_env'] = $_ENV['APP_ENV'] ?? 'production';
        $data['app_debug'] = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        // Asset-URLs
        $data['asset_url'] = $_ENV['ASSET_URL'] ?? '/assets';
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
            $data['current_locale'] = 'de';
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
                $data['csrf_token'] = $this->csrf->generateToken();
            }

            if (!isset($data['csrf_field'])) {
                $data['csrf_field'] = '<input type="hidden" name="_token" value="' .
                    htmlspecialchars($data['csrf_token'], ENT_QUOTES) . '">';
            }

        } catch (\Throwable $e) {
            // Graceful fallback
            $data['csrf_token'] = 'dev-token-' . uniqid();
            $data['csrf_field'] = '<input type="hidden" name="_token" value="' . $data['csrf_token'] . '">';
        }

        return $data;
    }

    /**
     * Content Post-Processing
     */
    private function postProcessContent(string $content, array $data): string
    {
        // Meta-Tags für CSRF in <head> einfügen
        if (isset($data['csrf_token']) && stripos($content, '</head>') !== false) {
            $csrfMeta = '<meta name="csrf-token" content="' .
                htmlspecialchars($data['csrf_token'], ENT_QUOTES) . '">';

            $content = preg_replace(
                '/(<\/head\s*>)/i',
                "\n{$csrfMeta}\n$1",
                $content,
                1
            );
        }

        return $content;
    }

    /**
     * Error Handling für Template-Rendering
     */
    private function handleRenderError(
        \Throwable $e,
        string $template,
        array $data,
        HttpStatus $status,
        array $headers
    ): Response {
        if ($_ENV['APP_DEBUG'] === 'true') {
            return $this->renderDebugError($e, $template);
        }

        return $this->renderProductionError();
    }

    /**
     * Debug Error Page
     */
    private function renderDebugError(\Throwable $e, string $template): Response
    {
        $errorHtml = '<!DOCTYPE html>
        <html lang=de>
        <head>
            <meta charset="UTF-8">
            <title>Template Error</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
                .error-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 20px rgba(0,0,0,0.1); }
                .error-title { color: #e74c3c; font-size: 24px; margin-bottom: 20px; }
                .error-message { background: #f8f9fa; padding: 15px; border-left: 4px solid #e74c3c; margin: 20px 0; }
                .error-details { margin: 20px 0; font-size: 14px; }
                .stack-trace { background: #2c3e50; color: #ecf0f1; padding: 20px; border-radius: 5px; overflow: auto; }
                pre { margin: 0; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-title">🚫 Template Rendering Error</div>
                
                <div class="error-message">
                    ' . htmlspecialchars($e->getMessage()) . '
                </div>
                
                <div class="error-details">
                    <strong>Template:</strong> ' . htmlspecialchars($template) . '<br>
                    <strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '<br>
                    <strong>Line:</strong> ' . $e->getLine() . '<br>
                    <strong>Template Engine:</strong> KickersCup Framework v2.0
                </div>
                
                <div class="stack-trace">
                    <strong>Stack Trace:</strong><br>
                    <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
                </div>
            </div>
        </body>
        </html>';

        return new Response(HttpStatus::INTERNAL_SERVER_ERROR, [], $errorHtml);
    }

    /**
     * Production Error Page
     */
    private function renderProductionError(): Response
    {
        $errorHtml = '<!DOCTYPE html>
        <html lang=de>
        <head>
            <meta charset="UTF-8">
            <title>Server Error</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                .error-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px20px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
                .error-title { color: #e74c3c; font-size: 36px; margin-bottom: 20px; }
                .error-message { color: #666; font-size: 18px; margin-bottom: 30px; }
                .error-button { background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
                .error-button:hover { background: #2980b9; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-title">🚫 Server Error</div>
                <div class="error-message">
                    Es tut uns leid, aber es ist ein unerwarteter Fehler aufgetreten.<br>
                    Bitte versuchen Sie es später erneut.
                </div>
                <a href="/" class="error-button">Zur Startseite</a>
            </div>
        </body>
        </html>';

        return new Response(HttpStatus::INTERNAL_SERVER_ERROR, [], $errorHtml);
    }
}