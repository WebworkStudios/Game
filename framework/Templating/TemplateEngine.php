<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Templating\Cache\TemplateCache;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TemplateEngine
{
    private array $globals = [];
    private array $paths = [];
    private array $templateCache = []; // ← NEU: Template-Pfad-Cache

    public function __construct(
        private readonly TemplateCache $cache,
        string                         $defaultPath = ''
    )
    {
        if (!empty($defaultPath)) {
            $this->addPath($defaultPath);
        }
    }

    public function addPath(string $path, string $namespace = ''): void
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException("Template path does not exist: {$path}");
        }

        $this->paths[$namespace] = rtrim($path, '/');

        // Cache invalidieren wenn Pfade sich ändern
        $this->templateCache = [];
    }

    public function addGlobal(string $name, mixed $value): void
    {
        $this->globals[$name] = $value;
    }

    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->findTemplate($template);
        $compiledPath = $this->cache->get($templatePath);

        // Merge globals with data
        $data = array_merge($this->globals, $data);

        return $this->executeTemplate($compiledPath, $data);
    }

    private function findTemplate(string $template): string
    {
        // ← NEU: Cache-Lookup zuerst
        if (isset($this->templateCache[$template])) {
            $cachedPath = $this->templateCache[$template];
            // Verify cached path still exists
            if (file_exists($cachedPath)) {
                return $cachedPath;
            }
            // Remove invalid cache entry
            unset($this->templateCache[$template]);
        }

        $resolvedPath = $this->resolveTemplatePath($template);

        // ← NEU: Erfolgreiche Auflösung cachen
        $this->templateCache[$template] = $resolvedPath;

        return $resolvedPath;
    }

    private function resolveTemplatePath(string $template): string
    {
        // Handle namespaced templates (@namespace/template.html)
        if (str_starts_with($template, '@')) {
            [$namespace, $template] = explode('/', $template, 2);
            $namespace = substr($namespace, 1);

            if (!isset($this->paths[$namespace])) {
                throw new InvalidArgumentException("Template namespace not found: {$namespace}");
            }

            $path = $this->paths[$namespace] . '/' . $template;
        } else {
            // ← OPTIMIERT: Nur default namespace checken statt alle
            if (!isset($this->paths[''])) {
                throw new InvalidArgumentException("No default template path configured");
            }
            $path = $this->paths[''] . '/' . $template;
        }

        // Add .html extension if missing
        if (!str_contains($template, '.')) {
            $path .= '.html';
        }

        if (!file_exists($path)) {
            throw new InvalidArgumentException("Template not found: {$path}");
        }

        return $path;
    }

    private function executeTemplate(string $compiledPath, array $data): string
    {
        $renderer = new TemplateRenderer($this, $data);

        ob_start();
        try {
            // ← VERBESSERT: Bessere Block-Verwaltung
            $_parentBlocks = $_parentBlocks ?? [];

            // Falls Parent-Blocks existieren, an Renderer weitergeben
            if (!empty($_parentBlocks)) {
                $renderer->setParentBlocks($_parentBlocks);
            }

            include $compiledPath;
            return ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw new RuntimeException(
                "Template execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Render with specific renderer (for block inheritance)
     */
    public function renderWithRenderer(string $template, TemplateRenderer $renderer): string
    {
        $templatePath = $this->findTemplate($template);
        $compiledPath = $this->cache->get($templatePath);

        ob_start();
        try {
            include $compiledPath;
            return ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw new RuntimeException(
                "Template execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * ← NEU: Cache-Verwaltung
     */
    public function clearTemplateCache(): void
    {
        $this->templateCache = [];
    }

    public function clearCompiledCache(): void
    {
        $this->cache->clear();
        $this->clearTemplateCache();
    }

    public function getTemplateCacheStats(): array
    {
        return [
            'cached_templates' => count($this->templateCache),
            'cache_entries' => array_keys($this->templateCache)
        ];
    }
}