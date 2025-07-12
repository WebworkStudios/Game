<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Templating\Cache\TemplateCache;
use Framework\Templating\Compiler\TemplateCompilerFactory;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Template Engine - Clean and robust implementation
 *
 * Features:
 * - Template inheritance (extends/blocks)
 * - Multiple template paths with namespaces
 * - Template caching with smart invalidation
 * - Global variables
 * - Include with data mapping
 * - Error handling and debugging
 * - Performance optimizations
 */
class TemplateEngine
{
    private array $globals = [];
    private array $paths = [];

    // Path cache for performance
    private array $templatePathCache = [];
    private array $compiledPathCache = [];

    public function __construct(
        private readonly TemplateCache $cache,
        string $defaultPath = ''
    ) {
        if (!empty($defaultPath)) {
            $this->addPath($defaultPath);
        }
    }

    /**
     * Add template search path with optional namespace
     */
    public function addPath(string $path, string $namespace = ''): void
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException("Template path does not exist: {$path}");
        }

        $this->paths[$namespace] = rtrim($path, '/');
        $this->clearCaches();
    }

    /**
     * Remove template path
     */
    public function removePath(string $namespace = ''): void
    {
        unset($this->paths[$namespace]);
        $this->clearCaches();
    }

    /**
     * Get all configured paths
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Add global variable available in all templates
     */
    public function addGlobal(string $name, mixed $value): void
    {
        $this->globals[$name] = $value;
    }

    /**
     * Add multiple global variables
     */
    public function addGlobals(array $globals): void
    {
        $this->globals = array_merge($this->globals, $globals);
    }

    /**
     * Remove global variable
     */
    public function removeGlobal(string $name): void
    {
        unset($this->globals[$name]);
    }

    /**
     * Get all global variables
     */
    public function getGlobals(): array
    {
        return $this->globals;
    }

    /**
     * Main render method - renders template with data
     */
    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->findTemplate($template);
        $compiledPath = $this->getCompiledPath($templatePath);

        // Merge globals with data (data takes precedence)
        $mergedData = array_merge($this->globals, $data);

        return $this->executeTemplate($compiledPath, $mergedData, $templatePath);
    }

    /**
     * Check if template exists
     */
    public function exists(string $template): bool
    {
        try {
            $this->findTemplate($template);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Include template (used by TemplateRenderer)
     */
    public function include(string $template, array $data = []): string
    {
        return $this->render($template, $data);
    }

    /**
     * Include template with data mapping (used by TemplateRenderer)
     */
    public function includeWith(string $template, string $variable, mixed $data): string
    {
        return $this->render($template, [$variable => $data]);
    }

    /**
     * Render with specific renderer (for block inheritance)
     */
    public function renderWithRenderer(string $template, TemplateRenderer $renderer): string
    {
        $templatePath = $this->findTemplate($template);
        $compiledPath = $this->getCompiledPath($templatePath);

        ob_start();
        try {
            include $compiledPath;
            return ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw new RuntimeException(
                "Template execution with renderer failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Clear all caches
     */
    public function clearCaches(): void
    {
        $this->templatePathCache = [];
        $this->compiledPathCache = [];
        $this->cache->clear();
    }

    /**
     * Clear compiled cache only
     */
    public function clearCompiledCache(): void
    {
        $this->compiledPathCache = [];
        $this->cache->clear();
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'template_path_cache_size' => count($this->templatePathCache),
            'compiled_path_cache_size' => count($this->compiledPathCache),
            'cached_templates' => array_keys($this->templatePathCache),
            'paths_configured' => count($this->paths),
            'namespaces' => array_keys($this->paths),
            'globals_count' => count($this->globals),
            'file_cache_stats' => $this->cache->getStats(),
        ];
    }

    /**
     * Get template source (for debugging)
     */
    public function getSource(string $template): string
    {
        $templatePath = $this->findTemplate($template);
        $content = file_get_contents($templatePath);

        if ($content === false) {
            throw new RuntimeException("Cannot read template source: {$templatePath}");
        }

        return $content;
    }

    /**
     * Get compiled source (for debugging)
     */
    public function getCompiledSource(string $template): string
    {
        $templatePath = $this->findTemplate($template);
        $compiledPath = $this->getCompiledPath($templatePath);

        $content = file_get_contents($compiledPath);

        if ($content === false) {
            throw new RuntimeException("Cannot read compiled template: {$compiledPath}");
        }

        return $content;
    }

    /**
     * Validate template syntax without executing
     */
    public function validateTemplate(string $template): array
    {
        $errors = [];

        try {
            $templatePath = $this->findTemplate($template);
            $content = file_get_contents($templatePath);

            if ($content === false) {
                $errors[] = "Cannot read template file";
                return $errors;
            }

            // The TemplateCache will handle compilation via the compiler
            $this->getCompiledPath($templatePath);

        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Get template metadata
     */
    public function getTemplateInfo(string $template): array
    {
        $templatePath = $this->findTemplate($template);
        $stat = stat($templatePath);

        return [
            'name' => $template,
            'path' => $templatePath,
            'size' => $stat['size'] ?? 0,
            'modified' => $stat['mtime'] ?? 0,
            'modified_date' => date('Y-m-d H:i:s', $stat['mtime'] ?? 0),
            'exists' => true,
            'compiled' => file_exists($this->getCompiledPath($templatePath)),
        ];
    }

    /**
     * Find template with caching
     */
    private function findTemplate(string $template): string
    {
        // Check cache first
        if (isset($this->templatePathCache[$template])) {
            $cachedPath = $this->templatePathCache[$template];
            if (file_exists($cachedPath)) {
                return $cachedPath;
            }
            // Remove invalid cache entry
            unset($this->templatePathCache[$template]);
        }

        $resolvedPath = $this->resolveTemplatePath($template);

        // Cache successful resolution
        $this->templatePathCache[$template] = $resolvedPath;

        // Prevent cache from growing too large
        if (count($this->templatePathCache) > 100) {
            $this->templatePathCache = array_slice(
                $this->templatePathCache, -50, null, true
            );
        }

        return $resolvedPath;
    }

    /**
     * Resolve template path with namespace support
     */
    private function resolveTemplatePath(string $template): string
    {
        // Handle namespaced templates (@namespace/template.html)
        if (str_starts_with($template, '@')) {
            [$namespace, $templateName] = explode('/', $template, 2);
            $namespace = substr($namespace, 1);

            if (!isset($this->paths[$namespace])) {
                throw new InvalidArgumentException("Template namespace not found: {$namespace}");
            }

            $path = $this->paths[$namespace] . '/' . $templateName;
        } else {
            // Use default namespace
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
            $availablePaths = array_map(function($namespace, $path) {
                return $namespace === '' ? $path : "@{$namespace}: {$path}";
            }, array_keys($this->paths), $this->paths);

            throw new InvalidArgumentException(
                "Template not found: {$template}\nSearched in: " . implode(', ', $availablePaths)
            );
        }

        return $path;
    }

    /**
     * Get compiled path with caching
     */
    private function getCompiledPath(string $templatePath): string
    {
        // Check cache first
        if (isset($this->compiledPathCache[$templatePath])) {
            return $this->compiledPathCache[$templatePath];
        }

        // Get compiled path from TemplateCache
        $compiledPath = $this->cache->get($templatePath);

        // Cache the compiled path
        $this->compiledPathCache[$templatePath] = $compiledPath;

        // Prevent cache from growing too large
        if (count($this->compiledPathCache) > 50) {
            $this->compiledPathCache = array_slice(
                $this->compiledPathCache, -25, null, true
            );
        }

        return $compiledPath;
    }

    /**
     * Execute compiled template with proper scope isolation
     */
    private function executeTemplate(string $compiledPath, array $data, string $originalPath): string
    {
        // Create renderer with template engine reference for includes
        $renderer = new TemplateRenderer($this, $data);

        ob_start();
        try {
            // Provide template context for debugging
            $_templatePath = $originalPath;
            $_parentBlocks = $_parentBlocks ?? [];

            // If parent blocks exist, pass them to renderer
            if (!empty($_parentBlocks)) {
                $renderer->setParentBlocks($_parentBlocks);
            }

            // Execute compiled template
            include $compiledPath;
            return ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();

            // Enhanced error reporting
            throw new RuntimeException(
                "Template execution failed in '{$originalPath}': {$e->getMessage()}\n" .
                "Compiled path: {$compiledPath}\n" .
                "Error at line: {$e->getLine()}",
                0,
                $e
            );
        }
    }
}