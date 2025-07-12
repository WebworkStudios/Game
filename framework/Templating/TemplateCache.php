<?php
declare(strict_types=1);

namespace Framework\Templating;

use RuntimeException;

/**
 * Template Cache - Handles compilation caching with dependency tracking
 */
class TemplateCache
{
    private const string CACHE_VERSION = '1.0';
    private const string CACHE_EXTENSION = '.php';

    public function __construct(
        private readonly string $cacheDir,
        private readonly bool $enabled = true
    )
    {
        $this->ensureCacheDirectory();
    }

    /**
     * Check if cached version is valid
     */
    public function isValid(string $template, array $dependencies = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $cacheFile = $this->getCacheFile($template);

        if (!file_exists($cacheFile)) {
            return false;
        }

        try {
            $cached = require $cacheFile;

            // Version check
            if (($cached['version'] ?? '') !== self::CACHE_VERSION) {
                return false;
            }

            // Check main template file
            $templatePath = $cached['template_path'] ?? '';
            if (!file_exists($templatePath)) {
                return false;
            }

            if (filemtime($templatePath) > ($cached['compiled_at'] ?? 0)) {
                return false;
            }

            // Check all dependencies
            foreach ($cached['dependencies'] ?? [] as $depPath => $depTime) {
                if (!file_exists($depPath)) {
                    return false;
                }

                if (filemtime($depPath) > $depTime) {
                    return false;
                }
            }

            return true;

        } catch (\Throwable $e) {
            // Invalid cache file
            error_log("Cache validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load compiled template from cache
     */
    public function load(string $template): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $cacheFile = $this->getCacheFile($template);

        if (!file_exists($cacheFile)) {
            return null;
        }

        try {
            $cached = require $cacheFile;
            return $cached['compiled'] ?? null;
        } catch (\Throwable $e) {
            error_log("Cache load error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Store compiled template in cache
     */
    public function store(string $template, string $templatePath, array $compiled, array $dependencies = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $cacheFile = $this->getCacheFile($template);
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true)) {
            return false;
        }

        // Prepare dependency timestamps
        $depTimestamps = [];
        foreach ($dependencies as $depPath) {
            if (file_exists($depPath)) {
                $depTimestamps[$depPath] = filemtime($depPath);
            }
        }

        $cacheData = [
            'version' => self::CACHE_VERSION,
            'template' => $template,
            'template_path' => $templatePath,
            'compiled_at' => time(),
            'dependencies' => $depTimestamps,
            'compiled' => $compiled,
            'stats' => [
                'tokens' => count($compiled['tokens'] ?? []),
                'blocks' => count($compiled['blocks'] ?? []),
                'memory_usage' => memory_get_usage(),
            ]
        ];

        $content = "<?php\n\n// Template cache for: {$template}\n";
        $content .= "// Generated at: " . date('Y-m-d H:i:s') . "\n";
        $content .= "// DO NOT EDIT - This file is auto-generated\n\n";
        $content .= "return " . var_export($cacheData, true) . ";\n";

        return file_put_contents($cacheFile, $content, LOCK_EX) !== false;
    }

    /**
     * Clear cache for specific template
     */
    public function clear(string $template): bool
    {
        $cacheFile = $this->getCacheFile($template);

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return true;
    }

    /**
     * Clear all cached templates
     */
    public function clearAll(): int
    {
        $cleared = 0;

        if (!is_dir($this->cacheDir)) {
            return $cleared;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                if (unlink($file->getPathname())) {
                    $cleared++;
                }
            }
        }

        return $cleared;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $stats = [
            'enabled' => $this->enabled,
            'cache_dir' => $this->cacheDir,
            'total_files' => 0,
            'total_size' => 0,
            'oldest_cache' => null,
            'newest_cache' => null,
        ];

        if (!is_dir($this->cacheDir)) {
            return $stats;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $stats['total_files']++;
                $stats['total_size'] += $file->getSize();

                $mtime = $file->getMTime();
                if ($stats['oldest_cache'] === null || $mtime < $stats['oldest_cache']) {
                    $stats['oldest_cache'] = $mtime;
                }
                if ($stats['newest_cache'] === null || $mtime > $stats['newest_cache']) {
                    $stats['newest_cache'] = $mtime;
                }
            }
        }

        return $stats;
    }

    /**
     * Generate cache file path
     */
    private function getCacheFile(string $template): string
    {
        // Normalize template path and create safe filename
        $normalized = str_replace(['/', '\\', '.'], '_', $template);
        $hash = substr(md5($template), 0, 8);

        return $this->cacheDir . '/' . $normalized . '_' . $hash . self::CACHE_EXTENSION;
    }

    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true)) {
            throw new RuntimeException("Cannot create cache directory: {$this->cacheDir}");
        }
    }
}