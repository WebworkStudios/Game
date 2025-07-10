<?php
declare(strict_types=1);

namespace Framework\Templating\Cache;

use Framework\Templating\Compiler\TemplateCompiler;

class TemplateCache
{
    private const string CACHE_EXTENSION = '.php';

    public function __construct(
        private readonly TemplateCompiler $compiler,
        private readonly string $cacheDir,
        private readonly bool $debug = false,
        private readonly int $checkInterval = 60
    ) {
        $this->ensureCacheDir();
    }

    public function get(string $templatePath): string
    {
        $compiledPath = $this->getCompiledPath($templatePath);

        if ($this->needsRecompile($templatePath, $compiledPath)) {
            $this->compile($templatePath, $compiledPath);
        }

        return $compiledPath;
    }

    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*' . self::CACHE_EXTENSION);

        foreach ($files as $file) {
            if (!unlink($file)) {
                return false;
            }
        }

        return true;
    }

    private function needsRecompile(string $templatePath, string $compiledPath): bool
    {
        if (!file_exists($compiledPath)) {
            return true;
        }

        // Development: Always check for changes
        if ($this->debug) {
            return filemtime($templatePath) > filemtime($compiledPath);
        }

        // Production: Only check every X seconds
        $compiledTime = filemtime($compiledPath);
        if ((time() - $compiledTime) < $this->checkInterval) {
            return false;
        }

        return filemtime($templatePath) > $compiledTime;
    }

    private function compile(string $templatePath, string $compiledPath): void
    {
        $content = file_get_contents($templatePath);
        if ($content === false) {
            throw new \RuntimeException("Cannot read template: {$templatePath}");
        }

        $compiled = $this->compiler->compile($content, $templatePath);

        $dir = dirname($compiledPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("Cannot create cache directory: {$dir}");
        }

        if (file_put_contents($compiledPath, $compiled, LOCK_EX) === false) {
            throw new \RuntimeException("Cannot write compiled template: {$compiledPath}");
        }

        // Invalidate OPcache
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($compiledPath, true);
        }
    }

    private function getCompiledPath(string $templatePath): string
    {
        $hash = hash('xxh3', $templatePath);
        $filename = basename($templatePath, '.html') . '_' . $hash . self::CACHE_EXTENSION;

        return $this->cacheDir . '/' . $filename;
    }

    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true)) {
            throw new \RuntimeException("Cannot create cache directory: {$this->cacheDir}");
        }
    }
}