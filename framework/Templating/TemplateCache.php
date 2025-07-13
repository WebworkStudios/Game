<?php
declare(strict_types=1);

namespace Framework\Templating;

use RuntimeException;

/**
 * Template Cache - Handles compilation caching with dependency tracking and tag system
 */
class TemplateCache
{
    private const string CACHE_VERSION = '1.0';
    private const string CACHE_EXTENSION = '.php';

    private array $pendingTags = [];

    public function __construct(
        private readonly string $cacheDir,
        private readonly bool   $enabled = true
    )
    {
        $this->ensureCacheDirectory();
    }

    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true)) {
            throw new RuntimeException("Cannot create cache directory: {$this->cacheDir}");
        }

        // Create subdirectories
        $this->ensureFragmentDirectory();
    }

    private function ensureFragmentDirectory(): void
    {
        $dirs = [
            $this->cacheDir . '/templates',
            $this->cacheDir . '/fragments',
            $this->cacheDir . '/tags'
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Tag current cache operation
     */
    public function tag(array $tags): self
    {
        $this->pendingTags = array_merge($this->pendingTags, $tags);
        return $this;
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
     * Generate cache file path
     */
    private function getCacheFile(string $template): string
    {
        // Normalize template path and create safe filename
        $normalized = str_replace(['/', '\\', '.'], '_', $template);
        $hash = substr(md5($template), 0, 8);

        return $this->cacheDir . '/templates/' . $normalized . '_' . $hash . self::CACHE_EXTENSION;
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

        $result = file_put_contents($cacheFile, $content, LOCK_EX) !== false;

        if ($result && !empty($this->pendingTags)) {
            $this->storeTagMapping($template, $this->pendingTags);
            $this->pendingTags = []; // Reset
        }

        return $result;
    }

    private function storeTagMapping(string $cacheKey, array $tags): void
    {
        foreach ($tags as $tag) {
            $tagFile = $this->getTagFile($tag);
            $existingKeys = [];

            if (file_exists($tagFile)) {
                try {
                    $existingKeys = require $tagFile;
                } catch (\Throwable) {
                    $existingKeys = [];
                }
            }

            if (!in_array($cacheKey, $existingKeys)) {
                $existingKeys[] = $cacheKey;
            }

            $content = "<?php\nreturn " . var_export($existingKeys, true) . ";\n";
            file_put_contents($tagFile, $content, LOCK_EX);
        }
    }

    private function getTagFile(string $tag): string
    {
        return $this->cacheDir . '/tags/' . $tag . '.php';
    }

    /**
     * Store fragment with TTL and tags
     */
    public function storeFragment(string $key, string $content, int $ttl = 3600, array $tags = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $this->ensureFragmentDirectory();

        $fragmentFile = $this->getFragmentFile($key);
        $fragmentData = [
            'version' => self::CACHE_VERSION,
            'content' => $content,
            'expires_at' => time() + $ttl,
            'created_at' => time(),
            'tags' => $tags,
            'key' => $key
        ];

        $result = file_put_contents($fragmentFile, "<?php\nreturn " . var_export($fragmentData, true) . ";\n", LOCK_EX) !== false;

        if ($result && !empty($tags)) {
            $this->storeTagMapping($key, $tags);
        }

        return $result;
    }

    private function getFragmentFile(string $key): string
    {
        $hash = substr(md5($key), 0, 8);
        return $this->cacheDir . '/fragments/' . $hash . '.php';
    }

    // Private helper methods

    /**
     * Get fragment if not expired
     */
    public function getFragment(string $key): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $fragmentFile = $this->getFragmentFile($key);

        if (!file_exists($fragmentFile)) {
            return null;
        }

        try {
            $data = require $fragmentFile;

            if (time() > ($data['expires_at'] ?? 0)) {
                $this->clearFragment($key);
                return null;
            }

            return $data['content'] ?? null;

        } catch (\Throwable) {
            return null;
        }
    }

    private function clearFragment(string $key): bool
    {
        $fragmentFile = $this->getFragmentFile($key);
        return file_exists($fragmentFile) ? unlink($fragmentFile) : false;
    }

    /**
     * Invalidate multiple tags at once
     */
    public function invalidateByTags(array $tags): int
    {
        $totalCleared = 0;

        foreach ($tags as $tag) {
            $totalCleared += $this->invalidateByTag($tag);
        }

        return $totalCleared;
    }

    /**
     * Invalidate all cache entries with specific tag
     */
    public function invalidateByTag(string $tag): int
    {
        $cleared = 0;
        $tagFile = $this->getTagFile($tag);

        if (!file_exists($tagFile)) {
            return 0;
        }

        try {
            $cacheKeys = require $tagFile;

            foreach ($cacheKeys as $key) {
                if ($this->clearByKey($key)) {
                    $cleared++;
                }
            }

            unlink($tagFile);

        } catch (\Throwable $e) {
            error_log("Tag invalidation error: " . $e->getMessage());
        }

        return $cleared;
    }

    private function clearByKey(string $key): bool
    {
        // Try template cache
        $templateFile = $this->getCacheFile($key);
        if (file_exists($templateFile)) {
            return unlink($templateFile);
        }

        // Try fragment cache
        $fragmentFile = $this->getFragmentFile($key);
        if (file_exists($fragmentFile)) {
            return unlink($fragmentFile);
        }

        return false;
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
            'templates' => $this->getDirectoryStats('/'),
            'fragments' => $this->getDirectoryStats('/fragments'),
            'tags' => $this->getDirectoryStats('/tags'),
            'total_files' => 0,
            'total_size' => 0,
            'oldest_cache' => null,
            'newest_cache' => null,
        ];

        // Calculate totals
        foreach (['templates', 'fragments', 'tags'] as $type) {
            $stats['total_files'] += $stats[$type]['files'];
            $stats['total_size'] += $stats[$type]['size'];
        }

        // Find oldest and newest
        if (is_dir($this->cacheDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->cacheDir)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $mtime = $file->getMTime();
                    if ($stats['oldest_cache'] === null || $mtime < $stats['oldest_cache']) {
                        $stats['oldest_cache'] = $mtime;
                    }
                    if ($stats['newest_cache'] === null || $mtime > $stats['newest_cache']) {
                        $stats['newest_cache'] = $mtime;
                    }
                }
            }
        }

        return $stats;
    }

    private function getDirectoryStats(string $subDir): array
    {
        $dir = $this->cacheDir . $subDir;
        $stats = ['files' => 0, 'size' => 0];

        if (!is_dir($dir)) {
            return $stats;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $stats['files']++;
                $stats['size'] += $file->getSize();
            }
        }

        return $stats;
    }
}