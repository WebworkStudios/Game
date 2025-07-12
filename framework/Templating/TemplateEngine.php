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

    // Unified cache for both template paths AND compiled paths
    private array $pathCache = [
        'templates' => [],    // template name -> full template path
        'compiled' => [],     // template path -> compiled path
    ];

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

        // Clear both caches when paths change
        $this->clearPathCache();
    }

    /**
     * Clear path cache - UNIFIED METHOD
     */
    public function clearPathCache(): void
    {
        $this->pathCache = [
            'templates' => [],
            'compiled' => [],
        ];
    }

    public function addGlobal(string $name, mixed $value): void
    {
        $this->globals[$name] = $value;
    }

    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->findTemplate($template);
        $compiledPath = $this->getCompiledPath($templatePath);

        // Merge globals with data
        $data = array_merge($this->globals, $data);

        return $this->executeTemplate($compiledPath, $data);
    }

    /**
     * Find template with unified caching - OPTIMIZED VERSION
     */
    private function findTemplate(string $template): string
    {
        // Check template cache first
        if (isset($this->pathCache['templates'][$template])) {
            $cachedPath = $this->pathCache['templates'][$template];
            // Verify cached path still exists
            if (file_exists($cachedPath)) {
                return $cachedPath;
            }
            // Remove invalid cache entry
            unset($this->pathCache['templates'][$template]);
        }

        $resolvedPath = $this->resolveTemplatePath($template);

        // Cache successful resolution
        $this->pathCache['templates'][$template] = $resolvedPath;

        // Prevent cache from growing too large
        if (count($this->pathCache['templates']) > 100) {
            $this->pathCache['templates'] = array_slice(
                $this->pathCache['templates'], -50, null, true
            );
        }

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
            // Only check default namespace
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

    /**
     * Get compiled path with unified caching - NEW METHOD
     */
    private function getCompiledPath(string $templatePath): string
    {
        // Check compiled path cache
        if (isset($this->pathCache['compiled'][$templatePath])) {
            return $this->pathCache['compiled'][$templatePath];
        }

        // Get compiled path from TemplateCache
        $compiledPath = $this->cache->get($templatePath);

        // Cache the compiled path
        $this->pathCache['compiled'][$templatePath] = $compiledPath;

        // Prevent cache from growing too large
        if (count($this->pathCache['compiled']) > 50) {
            $this->pathCache['compiled'] = array_slice(
                $this->pathCache['compiled'], -25, null, true
            );
        }

        return $compiledPath;
    }

    private function executeTemplate(string $compiledPath, array $data): string
    {
        $renderer = new TemplateRenderer($this, $data);

        ob_start();
        try {
            // Better block management
            $_parentBlocks = $_parentBlocks ?? [];

            // If parent blocks exist, pass them to renderer
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
        $compiledPath = $this->getCompiledPath($templatePath);

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
     * Clear compiled cache and path cache - UNIFIED METHOD
     */
    public function clearCompiledCache(): void
    {
        $this->cache->clear();
        $this->clearPathCache();
    }

    /**
     * Get unified cache statistics - IMPROVED VERSION
     */
    public function getCacheStats(): array
    {
        return [
            'template_cache_size' => count($this->pathCache['templates']),
            'compiled_cache_size' => count($this->pathCache['compiled']),
            'cached_templates' => array_keys($this->pathCache['templates']),
            'paths_configured' => count($this->paths),
            'namespaces' => array_keys($this->paths),
            'file_cache_stats' => $this->cache->getStats(),
        ];
    }
}