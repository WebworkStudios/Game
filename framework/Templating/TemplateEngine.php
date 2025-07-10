<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Templating\Cache\TemplateCache;
use InvalidArgumentException;
use RuntimeException;

class TemplateEngine
{
    private array $globals = [];
    private array $paths = [];

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

        // Handle namespaced templates (@namespace/template.html)
        if (str_starts_with($template, '@')) {
            [$namespace, $template] = explode('/', $template, 2);
            $namespace = substr($namespace, 1);

            if (!isset($this->paths[$namespace])) {
                throw new InvalidArgumentException("Template namespace not found: {$namespace}");
            }

            $path = $this->paths[$namespace] . '/' . $template;
        } else {
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
            // Make blocks available globally
            $_parentBlocks = $_parentBlocks ?? [];

            include $compiledPath;
            return ob_get_clean();
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new RuntimeException(
                "Template execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}