<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Http\HttpStatus;
use Framework\Http\Response;
use Framework\Localization\Translator;
use Framework\Security\Csrf;

/**
 * ViewRenderer - Integriert die neue SRP-konforme TemplateEngine in das Response System
 *
 * UPDATED: Angepasst für neue TemplateEngine-Architektur
 *
 * Verantwortlichkeiten:
 * - Template-Rendering zu HTTP-Response konvertieren
 * - Auto-Injection von Framework-Services (Translator, CSRF)
 * - Content-Type und HTTP-Header Management
 * - Graceful Handling von optionalen Dependencies
 * - CSRF-Token Integration für Security
 */
readonly class ViewRenderer
{
    public function __construct(
        private TemplateEngine $engine,
        private ?Translator    $translator = null,
        private ?Csrf          $csrf = null
    )
    {
    }

    /**
     * Rendert Template zu HTTP Response
     *
     * ENHANCED: Erweiterte Auto-Injection und besseres Error-Handling
     */
    public function render(
        string     $template,
        array      $data = [],
        HttpStatus $status = HttpStatus::OK,
        array      $headers = []
    ): Response
    {
        try {
            // Auto-inject Framework-Services
            $data = $this->injectFrameworkServices($data);

            // Auto-inject Global Template Variables
            $data = $this->injectGlobalVariables($data);

            // Render template content mit neuer Engine
            $content = $this->engine->render($template, $data);

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
     * Injiziert Framework-Services in Template-Daten
     */
    private function injectFrameworkServices(array $data): array
    {
        // Localization Services
        if ($this->translator !== null) {
            $data = $this->injectTranslationServices($data);
        }

        // Security Services
        if ($this->csrf !== null) {
            $data = $this->injectSecurityServices($data);
        }

        return $data;
    }

    /**
     * Injiziert Translation-Services
     */
    private function injectTranslationServices(array $data): array
    {
        try {
            // Current locale
            if (!isset($data['current_locale'])) {
                $data['current_locale'] = $this->translator->getLocale();
            }

            // Available locales
            if (!isset($data['available_locales'])) {
                $data['available_locales'] = $this->translator->getSupportedLocales();
            }

            // Translation helper function
            if (!isset($data['trans'])) {
                $data['trans'] = function (string $key, array $parameters = []) {
                    return $this->translator->translate($key, $parameters);
                };
            }

        } catch (\Throwable $e) {
            // Graceful fallback bei Translation-Fehlern
            $data['current_locale'] = 'de';
            $data['available_locales'] = ['de' => 'Deutsch'];
            $data['trans'] = function (string $key, array $parameters = []) {
                return $key; // Fallback: Return key as-is
            };
        }

        return $data;
    }

    /**
     * Injiziert Security-Services
     */
    private function injectSecurityServices(array $data): array
    {
        try {
            // CSRF Token für Forms
            if (!isset($data['csrf_token'])) {
                $data['csrf_token'] = $this->csrf->getToken();
            }

            // CSRF Token Field für HTML Forms
            if (!isset($data['csrf_token_field'])) {
                $data['csrf_token_field'] = $this->csrf->getTokenField();
            }

            // CSRF Meta Tag für JavaScript
            if (!isset($data['csrf_meta_tag'])) {
                $data['csrf_meta_tag'] = $this->csrf->getTokenMeta();
            }

        } catch (\Throwable $e) {
            // Graceful fallback bei CSRF-Fehlern
            $data['csrf_token'] = '';
            $data['csrf_token_field'] = '<!-- CSRF not available -->';
            $data['csrf_meta_tag'] = '<!-- CSRF meta not available -->';
        }

        return $data;
    }

    /**
     * Injiziert globale Template-Variablen
     */
    private function injectGlobalVariables(array $data): array
    {
        // Framework-Information
        if (!isset($data['framework_version'])) {
            $data['framework_version'] = '2.0.0';
        }

        if (!isset($data['framework_name'])) {
            $data['framework_name'] = 'KickersCup Framework';
        }

        // Environment-Information
        if (!isset($data['environment'])) {
            $data['environment'] = $_ENV['APP_ENV'] ?? 'production';
        }

        // Timestamp für Cache-Busting
        if (!isset($data['build_timestamp'])) {
            $data['build_timestamp'] = time();
        }

        // Request-Information
        if (!isset($data['request_uri'])) {
            $data['request_uri'] = $_SERVER['REQUEST_URI'] ?? '/';
        }

        if (!isset($data['request_method'])) {
            $data['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        }

        // Template-Helper-Functions
        $data = $this->injectHelperFunctions($data);

        return $data;
    }

    /**
     * Injiziert Helper-Functions für Templates
     */
    private function injectHelperFunctions(array $data): array
    {
        // URL-Helper
        if (!isset($data['url'])) {
            $data['url'] = function (string $path = '') {
                return $this->generateUrl($path);
            };
        }

        // Asset-Helper
        if (!isset($data['asset'])) {
            $data['asset'] = function (string $path) {
                return $this->generateAssetUrl($path);
            };
        }

        // Route-Helper
        if (!isset($data['route'])) {
            $data['route'] = function (string $name, array $parameters = []) {
                return $this->generateRoute($name, $parameters);
            };
        }

        // Date-Helper
        if (!isset($data['date_format'])) {
            $data['date_format'] = function (string $date, string $format = 'Y-m-d H:i:s') {
                return date($format, strtotime($date));
            };
        }

        // Number-Helper
        if (!isset($data['number_format'])) {
            $data['number_format'] = function (float $number, int $decimals = 0) {
                return number_format($number, $decimals, ',', '.');
            };
        }

        return $data;
    }

    /**
     * URL-Helper-Funktionen
     */
    private function generateUrl(string $path = ''): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function generateAssetUrl(string $path): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $assetPath = '/assets/' . ltrim($path, '/');

        // Cache-Busting in Production
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            $assetPath .= '?v=' . filemtime(public_path($assetPath));
        }

        return rtrim($baseUrl, '/') . $assetPath;
    }

    private function generateRoute(string $name, array $parameters = []): string
    {
        // Simplified route generation
        // In einer echten Implementierung würde hier der Router verwendet
        $routes = [
            'home' => '/',
            'team.overview' => '/team',
            'test.templates' => '/test/templates',
            'test.filters' => '/test/filters'
        ];

        $path = $routes[$name] ?? '/';

        // Replace parameters in path
        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }

        return $this->generateUrl($path);
    }

    /**
     * Post-Processing des gerenderten Contents
     */
    private function postProcessContent(string $content, array $data): string
    {
        // CSRF Meta Tag in HTML Head injizieren
        $content = $this->injectCsrfMeta($content, $data);

        // Performance-Optimierungen
        $content = $this->optimizeHtml($content);

        // Debug-Informationen hinzufügen (nur in Development)
        if (($data['environment'] ?? '') === 'development') {
            $content = $this->addDebugInfo($content, $data);
        }

        return $content;
    }

    /**
     * Injiziert CSRF Meta Tag in HTML Head
     */
    private function injectCsrfMeta(string $content, array $data): string
    {
        // Check if CSRF meta tag already present
        if (str_contains($content, 'name="csrf-token"')) {
            return $content;
        }

        // Check if we have a head section
        if (!str_contains($content, '<head>')) {
            return $content;
        }

        // Get CSRF meta tag
        $csrfMeta = $data['csrf_meta_tag'] ?? '';
        if (empty($csrfMeta) || str_contains($csrfMeta, 'not available')) {
            return $content;
        }

        // Inject after <head> tag
        $content = str_replace(
            '<head>',
            "<head>\n    " . $csrfMeta,
            $content
        );

        return $content;
    }

    /**
     * HTML-Optimierungen
     */
    private function optimizeHtml(string $content): string
    {
        // Remove unnecessary whitespace (nur in Production)
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            // Remove comments
            $content = preg_replace('/<!--.*?-->/s', '', $content);

            // Remove excessive whitespace
            $content = preg_replace('/\s+/', ' ', $content);

            // Remove whitespace around tags
            $content = preg_replace('/>\s+</', '><', $content);
        }

        return $content;
    }

    /**
     * Debug-Informationen hinzufügen
     */
    private function addDebugInfo(string $content, array $data): string
    {
        $debugInfo = "<!-- \n";
        $debugInfo .= "Template rendered at: " . date('Y-m-d H:i:s') . "\n";
        $debugInfo .= "Environment: " . ($data['environment'] ?? 'unknown') . "\n";
        $debugInfo .= "Request URI: " . ($data['request_uri'] ?? 'unknown') . "\n";
        $debugInfo .= "Template Engine: SRP-konforme TemplateEngine v2.0\n";
        $debugInfo .= "-->\n";

        // Add before </body> tag
        if (str_contains($content, '</body>')) {
            $content = str_replace('</body>', $debugInfo . '</body>', $content);
        } else {
            $content .= $debugInfo;
        }

        return $content;
    }

    /**
     * Error-Handling für Template-Rendering
     */
    private function handleRenderError(
        \Throwable $e,
        string     $template,
        array      $data,
        HttpStatus $status,
        array      $headers
    ): Response
    {
        // Log the error
        error_log("ViewRenderer Error: " . $e->getMessage() . " in template: " . $template);

        // In Development: Detaillierte Fehlermeldung
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $errorContent = $this->renderErrorPage($e, $template, $data);
            $headers['Content-Type'] = 'text/html; charset=UTF-8';
            return new Response(HttpStatus::INTERNAL_SERVER_ERROR, $headers, $errorContent);
        }

        // In Production: Generische Fehlermeldung
        $errorContent = $this->renderProductionError();
        $headers['Content-Type'] = 'text/html; charset=UTF-8';
        return new Response(HttpStatus::INTERNAL_SERVER_ERROR, $headers, $errorContent);
    }

    /**
     * Detaillierte Fehlerseite für Development
     */
    private function renderErrorPage(\Throwable $e, string $template, array $data): string
    {
        $errorHtml = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Template Rendering Error</title>
            <style>
                body { font-family: monospace; margin: 20px; background: #f5f5f5; }
                .error-container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .error-title { color: #e74c3c; font-size: 24px; margin-bottom: 20px; }
                .error-message { background: #ffebee; padding: 15px; border-left: 4px solid #e74c3c; margin: 10px 0; }
                .error-details { background: #f8f9fa; padding: 15px; border-radius: 3px; margin: 10px 0; }
                .stack-trace { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 3px; overflow-x: auto; }
                pre { margin: 0; white-space: pre-wrap; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-title">⚠️ Template Rendering Error</div>
                
                <div class="error-message">
                    <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
                </div>
                
                <div class="error-details">
                    <strong>Template:</strong> ' . htmlspecialchars($template) . '<br>
                    <strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '<br>
                    <strong>Line:</strong> ' . $e->getLine() . '<br>
                    <strong>Template Engine:</strong> SRP-konforme TemplateEngine v2.0
                </div>
                
                <div class="stack-trace">
                    <strong>Stack Trace:</strong><br>
                    <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
                </div>
            </div>
        </body>
        </html>';

        return $errorHtml;
    }

    /**
     * Generische Fehlerseite für Production
     */
    private function renderProductionError(): string
    {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Server Error</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                .error-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 20px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
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
    }
}

// =============================================================================
// HELPER FUNCTIONS - Für bessere Template-Integration
// =============================================================================

/**
 * Global Helper Function für Asset-URLs
 */
function asset(string $path): string
{
    $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
    return rtrim($baseUrl, '/') . '/assets/' . ltrim($path, '/');
}

/**
 * Global Helper Function für Public-Path
 */
function public_path(string $path = ''): string
{
    return __DIR__ . '/../../public/' . ltrim($path, '/');
}

/**
 * Global Helper Function für Route-URLs
 */
function route(string $name, array $parameters = []): string
{
    $routes = [
        'home' => '/',
        'team.overview' => '/team',
        'test.templates' => '/test/templates',
        'test.filters' => '/test/filters'
    ];

    $path = $routes[$name] ?? '/';

    foreach ($parameters as $key => $value) {
        $path = str_replace('{' . $key . '}', $value, $path);
    }

    return $path;
}