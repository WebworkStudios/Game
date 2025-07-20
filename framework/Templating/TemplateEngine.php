<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Templating\Parsing\{ControlFlowParser, ParsedTemplate, TemplateParser, TemplatePathResolver, TemplateTokenizer};
use Framework\Templating\Rendering\{TemplateRenderer, TemplateVariableResolver};
use Framework\Templating\Tokens\{TemplateToken, TextToken, VariableToken, ControlToken};

/**
 * TemplateEngine - Robuste Template-Rendering-Engine mit Cache-Fallbacks
 *
 * FEATURES:
 * - Robust cache handling mit Fallback-Strategien
 * - Windows-kompatible Fehlerbehandlung
 * - Emergency rendering bei kritischen Fehlern
 * - ParsedTemplate corruption recovery
 * - Multiple fallback layers
 */
class TemplateEngine
{
    private readonly TemplateParser $parser;
    private readonly TemplateRenderer $renderer;

    public function __construct(
        private readonly TemplatePathResolver $pathResolver,
        private readonly TemplateCache $cache,
        FilterManager $filterManager
    ) {
        // Parser-Pipeline erstellen
        $tokenizer = new TemplateTokenizer();
        $controlFlowParser = new ControlFlowParser();
        $this->parser = new TemplateParser($tokenizer, $controlFlowParser, $pathResolver);

        // Renderer-Pipeline erstellen
        $variableResolver = new TemplateVariableResolver();
        $this->renderer = new TemplateRenderer($variableResolver, $filterManager, $pathResolver);
    }

    /**
     * Template mit Caching rendern
     */
    public function renderCached(string $template, array $data = [], int $ttl = 0): string
    {
        if ($ttl <= 0) {
            return $this->render($template, $data);
        }

        return $this->renderWidget($template, $data, $ttl);
    }

    /**
     * ROBUST: Hauptmethode - Template rendern mit Cache-Fallback-Strategie
     */
    public function render(string $template, array $data = []): string
    {
        try {
            $templatePath = $this->pathResolver->resolve($template);

            // ROBUST: Cache mit Fallback-Strategie
            $cachedResult = $this->tryLoadFromCache($template, $templatePath, $data);
            if ($cachedResult !== null) {
                return $cachedResult;
            }

            // ROBUST: Template parsing mit Error Handling
            $parsedTemplate = $this->safeParseTemplate($template, $templatePath);

            // ROBUST: Cache storage mit Error Handling
            $this->safeCacheTemplate($template, $templatePath, $parsedTemplate);

            // ROBUST: Template rendering
            return $this->safeRenderTemplate($parsedTemplate, $data);

        } catch (\Throwable $e) {
            error_log("Template render error for '{$template}': " . $e->getMessage());

            // EMERGENCY: Fallback zu einfachem Template-Loading
            return $this->emergencyRender($template, $data);
        }
    }

    /**
     * SAFE: Cache Loading mit Fallback
     */
    private function tryLoadFromCache(string $template, string $templatePath, array $data): ?string
    {
        try {
            // Check cache validity
            if (!$this->cache->isValid($template, [$templatePath])) {
                return null;
            }

            // Load from cache
            $compiled = $this->cache->load($template);
            if ($compiled === null) {
                return null;
            }

            // CRITICAL: Safe ParsedTemplate deserialization
            $parsedTemplate = $this->safeDeserializeParsedTemplate($compiled);
            if ($parsedTemplate === null) {
                // Cache corruption detected - clear it
                $this->cache->forget($template);
                return null;
            }

            // Render cached template with current data
            return $this->renderer->render($parsedTemplate, $data);

        } catch (\Throwable $e) {
            error_log("Cache load error for '{$template}': " . $e->getMessage());

            // Clear potentially corrupted cache
            try {
                $this->cache->forget($template);
            } catch (\Throwable $clearError) {
                error_log("Cache clear error: " . $clearError->getMessage());
            }

            return null;
        }
    }

    /**
     * SAFE: ParsedTemplate Deserialisierung mit Fallbacks
     */
    private function safeDeserializeParsedTemplate(array $compiled): ?ParsedTemplate
    {
        try {
            // Try normal deserialization
            return ParsedTemplate::fromArray($compiled);

        } catch (\Throwable $e) {
            error_log("ParsedTemplate deserialization error: " . $e->getMessage());

            // Try manual reconstruction
            try {
                return $this->manualReconstructParsedTemplate($compiled);
            } catch (\Throwable $manualError) {
                error_log("Manual ParsedTemplate reconstruction failed: " . $manualError->getMessage());
                return null;
            }
        }
    }

    /**
     * FALLBACK: Manual ParsedTemplate Reconstruction
     */
    private function manualReconstructParsedTemplate(array $compiled): ParsedTemplate
    {
        // Extract basic data
        $templatePath = $compiled['template_path'] ?? '';
        $parentTemplate = $compiled['parent_template'] ?? null;
        $dependencies = $compiled['dependencies'] ?? [];

        // Try to reconstruct tokens manually
        $tokens = [];
        $rawTokens = $compiled['tokens'] ?? [];

        foreach ($rawTokens as $tokenData) {
            try {
                $token = $this->safeReconstructToken($tokenData);
                if ($token !== null) {
                    $tokens[] = $token;
                }
            } catch (\Throwable $tokenError) {
                error_log("Token reconstruction failed: " . $tokenError->getMessage());
                // Create fallback text token
                $tokens[] = new TextToken('<!-- Token error -->');
            }
        }

        // Reconstruct blocks (simplified)
        $blocks = [];
        $rawBlocks = $compiled['blocks'] ?? [];

        foreach ($rawBlocks as $blockName => $blockTokens) {
            $blocks[$blockName] = []; // Simplified - skip block reconstruction for now
        }

        return new ParsedTemplate($tokens, $templatePath, $parentTemplate, $blocks, $dependencies);
    }

    /**
     * SAFE: Token Reconstruction
     */
    private function safeReconstructToken(array $tokenData): ?TemplateToken
    {
        $type = $tokenData['type'] ?? 'text';

        try {
            return match ($type) {
                'text' => new TextToken($tokenData['content'] ?? ''),
                'variable' => new VariableToken(
                    $tokenData['variable'] ?? '',
                    $tokenData['filters'] ?? [],
                    $tokenData['should_escape'] ?? true
                ),
                'control' => new ControlToken(
                    $tokenData['command'] ?? '',
                    $tokenData['expression'] ?? '',
                    [], // Skip children for safety
                    [],
                    $tokenData['metadata'] ?? []
                ),
                default => new TextToken('<!-- Unknown token -->')
            };
        } catch (\Throwable $e) {
            error_log("Token creation error for type '{$type}': " . $e->getMessage());
            return new TextToken('<!-- Token error -->');
        }
    }

    /**
     * SAFE: Template Parsing
     */
    private function safeParseTemplate(string $template, string $templatePath): ParsedTemplate
    {
        try {
            $content = file_get_contents($templatePath);
            if ($content === false) {
                throw new \RuntimeException("Cannot read template file: {$templatePath}");
            }

            return $this->parser->parse($content, $templatePath);

        } catch (\Throwable $e) {
            error_log("Template parsing error for '{$template}': " . $e->getMessage());

            // EMERGENCY: Create minimal ParsedTemplate
            $textToken = new TextToken(
                "<!-- Template parsing error: " . htmlspecialchars($e->getMessage()) . " -->"
            );

            return new ParsedTemplate([$textToken], $templatePath, null, [], [$templatePath]);
        }
    }

    /**
     * SAFE: Template Caching
     */
    private function safeCacheTemplate(string $template, string $templatePath, ParsedTemplate $parsedTemplate): void
    {
        try {
            $this->cache->store(
                $template,
                $templatePath,
                $parsedTemplate->toArray(),
                $parsedTemplate->getDependencies()
            );
        } catch (\Throwable $e) {
            error_log("Template caching error for '{$template}': " . $e->getMessage());
            // Continue without caching
        }
    }

    /**
     * SAFE: Template Rendering
     */
    private function safeRenderTemplate(ParsedTemplate $parsedTemplate, array $data): string
    {
        try {
            return $this->renderer->render($parsedTemplate, $data);
        } catch (\Throwable $e) {
            error_log("Template rendering error: " . $e->getMessage());

            // FALLBACK: Simple token-by-token rendering
            return $this->emergencyTokenRendering($parsedTemplate->getTokens(), $data);
        }
    }

    /**
     * EMERGENCY: Fallback Template Rendering
     */
    private function emergencyRender(string $template, array $data): string
    {
        try {
            $templatePath = $this->pathResolver->resolve($template);
            $content = file_get_contents($templatePath);

            if ($content === false) {
                return "<!-- Template not found: {$template} -->";
            }

            // VERY BASIC: Simple variable replacement without proper parsing
            return $this->basicVariableReplacement($content, $data);

        } catch (\Throwable $e) {
            error_log("Emergency render failed for '{$template}': " . $e->getMessage());
            return "<!-- Template system error -->";
        }
    }

    /**
     * EMERGENCY: Basic Variable Replacement
     */
    private function basicVariableReplacement(string $content, array $data): string
    {
        // Very basic {{ variable }} replacement
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $placeholder = '{{ ' . $key . ' }}';
                $replacement = htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $content = str_replace($placeholder, $replacement, $content);
            }
        }

        // Remove unresolved variables
        $content = preg_replace('/\{\{\s*[^}]+\s*}}/', '<!-- unresolved variable -->', $content);

        return $content;
    }

    /**
     * EMERGENCY: Simple Token Rendering
     */
    private function emergencyTokenRendering(array $tokens, array $data): string
    {
        $output = '';

        foreach ($tokens as $token) {
            try {
                if (method_exists($token, 'getContent')) {
                    $output .= $token->getContent();
                } elseif (method_exists($token, 'getVariable')) {
                    $varName = $token->getVariable();
                    $value = $data[$varName] ?? '';
                    $output .= htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            } catch (\Throwable $e) {
                $output .= '<!-- Token error -->';
            }
        }

        return $output;
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

            // Fallback: Render without caching
            return $this->render($template, $data);
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
     * Cache leeren für spezifisches Template
     */
    public function clearCache(string $template): bool
    {
        try {
            return $this->cache->forget($template);
        } catch (\Throwable $e) {
            error_log("Cache clear error for '{$template}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kompletten Cache leeren
     */
    public function clearAllCache(): bool
    {
        try {
            return $this->cache->clear();
        } catch (\Throwable $e) {
            error_log("Cache clear all error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Debug-Informationen abrufen
     */
    public function getDebugInfo(string $template): array
    {
        try {
            $templatePath = $this->pathResolver->resolve($template);

            return [
                'template' => $template,
                'path' => $templatePath,
                'exists' => file_exists($templatePath),
                'readable' => is_readable($templatePath),
                'size' => file_exists($templatePath) ? filesize($templatePath) : 0,
                'modified' => file_exists($templatePath) ? filemtime($templatePath) : 0,
                'cached' => $this->cache->isValid($template, [$templatePath]),
            ];
        } catch (\Throwable $e) {
            return [
                'template' => $template,
                'error' => $e->getMessage(),
            ];
        }
    }
}