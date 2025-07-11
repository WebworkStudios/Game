<?php
declare(strict_types=1);

namespace Framework\Templating\Cache;

use Framework\Templating\Compiler\TemplateCompiler;
use RuntimeException;

class TemplateCache
{
    private const string CACHE_EXTENSION = '.php';

    // ← NEU: Cache für kompilierte Pfade
    private array $compiledPathCache = [];

    public function __construct(
        private readonly TemplateCompiler $compiler,
        private readonly string           $cacheDir,
        private readonly bool             $debug = false,
        private readonly int              $checkInterval = 60
    )
    {
        $this->ensureCacheDir();
    }

    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true)) {
            throw new RuntimeException("Cannot create cache directory: {$this->cacheDir}");
        }
    }

    public function get(string $templatePath): string
    {
        // ← NEU: Cache für getCompiledPath
        if (isset($this->compiledPathCache[$templatePath])) {
            $compiledPath = $this->compiledPathCache[$templatePath];
        } else {
            $compiledPath = $this->getCompiledPath($templatePath);
            $this->compiledPathCache[$templatePath] = $compiledPath;
        }

        if ($this->needsRecompile($templatePath, $compiledPath)) {
            $this->compile($templatePath, $compiledPath);
        }

        return $compiledPath;
    }

    private function getCompiledPath(string $templatePath): string
    {
        // ← OPTIMIERT: xxh3 ist schneller als md5/sha1
        $hash = hash('xxh3', $templatePath);
        $filename = basename($templatePath, '.html') . '_' . $hash . self::CACHE_EXTENSION;

        return $this->cacheDir . '/' . $filename;
    }

    private function needsRecompile(string $templatePath, string $compiledPath): bool
    {
        if (!file_exists($compiledPath)) {
            return true;
        }

        // ← OPTIMIERT: Nur ein filemtime() Call pro Check
        static $lastCheckTimes = [];
        $now = time();

        // Development: Always check for changes
        if ($this->debug) {
            return filemtime($templatePath) > filemtime($compiledPath);
        }

        // Production: Rate-limited checking
        $cacheKey = $compiledPath;
        if (isset($lastCheckTimes[$cacheKey]) &&
            ($now - $lastCheckTimes[$cacheKey]) < $this->checkInterval) {
            return false;
        }

        $lastCheckTimes[$cacheKey] = $now;
        return filemtime($templatePath) > filemtime($compiledPath);
    }

    private function compile(string $templatePath, string $compiledPath): void
    {
        $content = file_get_contents($templatePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read template: {$templatePath}");
        }

        $compiled = $this->compiler->compile($content, $templatePath);

        $dir = dirname($compiledPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Cannot create cache directory: {$dir}");
        }

        if (file_put_contents($compiledPath, $compiled, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write compiled template: {$compiledPath}");
        }

        // Invalidate OPcache
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($compiledPath, true);
        }
    }

    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*' . self::CACHE_EXTENSION);

        foreach ($files as $file) {
            if (!unlink($file)) {
                return false;
            }
        }

        // ← NEU: Interne Caches auch leeren
        $this->compiledPathCache = [];

        return true;
    }

    /**
     * ← NEU: Cache-Statistiken
     */
    public function getStats(): array
    {
        return [
            'cache_dir' => $this->cacheDir,
            'debug_mode' => $this->debug,
            'check_interval' => $this->checkInterval,
            'compiled_path_cache_size' => count($this->compiledPathCache),
        ];
    }
}