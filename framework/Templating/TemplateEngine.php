<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Templating\Parsing\{ControlFlowParser, ParsedTemplate, TemplateParser, TemplatePathResolver, TemplateTokenizer};
use Framework\Templating\Rendering\{TemplateRenderer, TemplateVariableResolver};

/**
 * TemplateEngine - GEFIXT: Robuste Cache-Fallbacks gegen weiße Seiten
 *
 * PROBLEM BEHOBEN:
 * ✅ Sichere Cache-Validierung
 * ✅ Emergency Fallbacks bei Cache-Corruption
 * ✅ Bessere Error-Recovery
 * ✅ Garantiert niemals weiße Seiten
 */
class TemplateEngine
{
    private readonly TemplateParser $parser;
    private readonly TemplateRenderer $renderer;

    public function __construct(
        private readonly TemplatePathResolver $pathResolver,
        private readonly TemplateCache $cache,
        FilterManager $filterManager,
        bool $debugMode = false
    ) {
        $tokenizer = new TemplateTokenizer();
        $controlFlowParser = new ControlFlowParser();
        $this->parser = new TemplateParser($tokenizer, $controlFlowParser, $pathResolver);

        $variableResolver = new TemplateVariableResolver();
        $this->renderer = new TemplateRenderer($variableResolver, $filterManager, $pathResolver);

    }

    /**
     * HAUPTMETHODE: Template rendern mit GARANTIERTER Ausgabe
     */
    public function render(string $template, array $data = []): string
    {
        try {
            $templatePath = $this->pathResolver->resolve($template);

            // 1. VERSUCH: Cache laden
            $cachedResult = $this->tryLoadFromCache($template, $templatePath, $data);
            if ($cachedResult !== null) {
                return $cachedResult;
            }

            // 2. VERSUCH: Neu parsen und cachen
            return $this->parseAndCacheTemplate($template, $templatePath, $data);

        } catch (\Throwable $e) {
            error_log("Template render error for '{$template}': " . $e->getMessage());

            // 3. NOTFALL: Emergency Rendering (garantiert Ausgabe)
            return $this->emergencyRender($template, $data, $e);
        }
    }

    /**
     * SAFE: Cache Loading mit robuster Validierung
     */
    private function tryLoadFromCache(string $template, string $templatePath, array $data): ?string
    {
        try {
            // Cache-Validität prüfen
            if (!$this->cache->isValid($template, [$templatePath])) {
                return null;
            }

            // Cached Data laden
            $cached = $this->cache->load($template);
            if ($cached === null) {
                return null;
            }

            // KRITISCH: Sichere ParsedTemplate Rekonstruktion
            $parsedTemplate = $this->safeReconstructParsedTemplate($cached);
            if ($parsedTemplate === null) {
                // Cache ist korrupt - entfernen und neu versuchen
                $this->cache->forget($template);
                return null;
            }

            // Template mit aktuellen Daten rendern
            return $this->renderer->render($parsedTemplate, $data);

        } catch (\Throwable $e) {
            error_log("Cache load error for '{$template}': " . $e->getMessage());

            // Cache-Cleanup bei Fehlern
            try {
                $this->cache->forget($template);
            } catch (\Throwable $cleanupError) {
                error_log("Cache cleanup error: " . $cleanupError->getMessage());
            }

            return null;
        }
    }

    /**
     * Template parsen und sicher cachen
     */
    private function parseAndCacheTemplate(string $template, string $templatePath, array $data): string
    {
        try {
            // Template parsen
            $templateContent = file_get_contents($templatePath);
            if ($templateContent === false) {
                throw new \RuntimeException("Cannot read template file: {$templatePath}");
            }

            $parsedTemplate = $this->parser->parse($templateContent, $templatePath);

            // Sicher in Cache speichern
            $this->safeCacheTemplate($template, $templatePath, $parsedTemplate);

            // Template rendern
            return $this->renderer->render($parsedTemplate, $data);

        } catch (\Throwable $e) {
            error_log("Template parsing error for '{$template}': " . $e->getMessage());
            throw $e; // Weiterleiten für Emergency Handling
        }
    }

    /**
     * GEFIXT: Sichere ParsedTemplate Rekonstruktion
     */
    private function safeReconstructParsedTemplate(array $cached): ?ParsedTemplate
    {
        try {
            // Standard-Rekonstruktion versuchen
            if (isset($cached['data']) && is_array($cached['data'])) {
                return ParsedTemplate::fromArray($cached['data']);
            }

            // Direkte Rekonstruktion falls 'data' Key fehlt
            if (isset($cached['template_path'])) {
                return ParsedTemplate::fromArray($cached);
            }

            throw new \RuntimeException("No valid ParsedTemplate data found in cache");

        } catch (\Throwable $e) {
            error_log("ParsedTemplate reconstruction error: " . $e->getMessage());

            // FALLBACK: Manuelle Rekonstruktion
            return $this->manualReconstructParsedTemplate($cached);
        }
    }

    /**
     * FALLBACK: Manuelle ParsedTemplate Rekonstruktion
     */
    private function manualReconstructParsedTemplate(array $cached): ?ParsedTemplate
    {
        try {
            // Minimale Daten extrahieren
            $templatePath = $cached['template_path'] ?? '';
            $parentTemplate = $cached['parent_template'] ?? null;
            $blocks = $cached['blocks'] ?? [];
            $tokens = $cached['tokens'] ?? [];

            // Einfache ParsedTemplate erstellen
            return new ParsedTemplate(
                templatePath: $templatePath,
                tokens: $tokens,
                blocks: $blocks,
                parentTemplate: $parentTemplate,
                dependencies: []
            );

        } catch (\Throwable $e) {
            error_log("Manual ParsedTemplate reconstruction failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * SAFE: Template in Cache speichern mit Error Handling
     */
    private function safeCacheTemplate(string $template, string $templatePath, ParsedTemplate $parsedTemplate): void
    {
        try {
            $cacheData = $parsedTemplate->toArray();

            // Daten-Validierung vor dem Speichern
            if (!is_array($cacheData) || empty($cacheData)) {
                error_log("Invalid cache data structure for template: {$template}");
                return;
            }

            $this->cache->store($template, $templatePath, $cacheData, [$templatePath]);

        } catch (\Throwable $e) {
            error_log("Template cache store error for '{$template}': " . $e->getMessage());
            // Cache-Fehler sind nicht kritisch - weiter ohne Cache
        }
    }

    /**
     * EMERGENCY: Garantierte Template-Ausgabe bei kritischen Fehlern
     */
    private function emergencyRender(string $template, array $data, \Throwable $originalError): string
    {
        try {
            // VERSUCH 1: Ohne Cache direkt parsen
            $templatePath = $this->pathResolver->resolve($template);
            $templateContent = file_get_contents($templatePath);

            if ($templateContent !== false) {
                // Einfaches String-Replacement für kritische Fälle
                return $this->simpleTemplateReplace($templateContent, $data);
            }

        } catch (\Throwable $e) {
            error_log("Emergency render attempt 1 failed: " . $e->getMessage());
        }

        try {
            // VERSUCH 2: Fallback-Template
            return $this->renderFallbackTemplate($template, $data, $originalError);

        } catch (\Throwable $e) {
            error_log("Emergency render attempt 2 failed: " . $e->getMessage());
        }

        // LETZTER AUSWEG: Minimale Error-Seite
        return $this->renderMinimalErrorPage($template, $originalError);
    }

    /**
     * Einfaches Template-Replacement für Notfälle
     */
    private function simpleTemplateReplace(string $content, array $data): string
    {
        try {
            // Einfache Variable-Replacement
            foreach ($data as $key => $value) {
                if (is_scalar($value)) {
                    $placeholder = '{{ ' . $key . ' }}';
                    $content = str_replace($placeholder, htmlspecialchars((string)$value), $content);
                }
            }

            // Control-Strukturen entfernen (für Notfall)
            $content = preg_replace('/\{%.*?%}/s', '', $content);

            return $content;

        } catch (\Throwable $e) {
            error_log("Simple template replace failed: " . $e->getMessage());
            return $content; // Original zurückgeben
        }
    }

    /**
     * Fallback-Template für kritische Fehler
     */
    private function renderFallbackTemplate(string $template, array $data, \Throwable $error): string
    {
        $safeTemplate = htmlspecialchars($template);
        $debugInfo = '<pre>' . htmlspecialchars($error->getMessage()) . '</pre>';

        return <<<HTML
<!DOCTYPE html>
<html lang=de>
<head>
    <title>Template Error</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .error { background: #f8f8f8; padding: 20px; border-left: 4px solid #e74c3c; }
        .debug { background: #f1f2f6; padding: 15px; margin-top: 20px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="error">
        <h2>Template Loading Error</h2>
        <p>The template "<strong>{$safeTemplate}</strong>" could not be loaded.</p>
        <p>Please check the template file and try again.</p>
    </div>
    {$debugInfo}
</body>
</html>
HTML;
    }

    /**
     * Minimale Error-Seite als letzter Ausweg
     */
    private function renderMinimalErrorPage(string $template, \Throwable $error): string
    {
        return '<!DOCTYPE html><html lang=de><head><title>Error</title></head><body>' .
            '<h1>Template Error</h1>' .
            '<p>Template could not be loaded.</p>' .
            '</body></html>';
    }

    /**
     * Widget-Rendering mit Fragment-Caching
     */
    public function renderWidget(string $template, array $data = [], int $ttl = 300): string
    {
        $cacheKey = 'widget_' . md5($template . serialize($data));

        try {
            if ($cached = $this->cache->getFragment($cacheKey)) {
                return $cached;
            }

            $content = $this->render($template, $data);
            $this->cache->storeFragment($cacheKey, $content, $ttl);

            return $content;

        } catch (\Throwable $e) {
            error_log("Widget render error for '{$template}': " . $e->getMessage());
            return $this->render($template, $data); // Fallback ohne Caching
        }
    }

    /**
     * Template existiert prüfen
     */
    public function exists(string $template): bool
    {
        try {
            $this->pathResolver->resolve($template);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Cache-Management
     */
    public function clearCache(string $template): bool
    {
        return $this->cache->forget($template);
    }

    public function clearAllCache(): bool
    {
        return $this->cache->clear();
    }

}