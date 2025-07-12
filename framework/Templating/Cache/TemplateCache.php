<?php
declare(strict_types=1);

namespace Framework\Templating\Cache;

use Framework\Templating\Compiler\TemplateCompiler;
use RuntimeException;

/**
 * Template Cache Manager - Handles compilation and caching of templates
 *
 * Features:
 * - Smart recompilation based on file modification times
 * - Development vs Production caching strategies
 * - Rate-limited file checking in production
 * - OPcache integration
 * - Memory-efficient hash-based cache keys
 * - Comprehensive error handling
 */
class TemplateCache
{
    private const string CACHE_EXTENSION = '.php';

    // Static cache for file modification times to avoid repeated checks
    private static array $lastCheckTimes = [];

    public function __construct(
        private readonly TemplateCompiler $compiler,
        private readonly string $cacheDir,
        private readonly bool $debug = false,
        private readonly int $checkInterval = 60
    ) {
        $this->ensureCacheDir();
    }

    /**
     * Get compiled template path, compiling if necessary
     */
    public function get(string $templatePath): string
    {
        $compiledPath = $this->getCompiledPath($templatePath);

        if ($this->needsRecompile($templatePath, $compiledPath)) {
            $this->compile($templatePath, $compiledPath);
        }

        return $compiledPath;
    }

    /**
     * Check if template exists in cache and is up to date
     */
    public function exists(string $templatePath): bool
    {
        $compiledPath = $this->getCompiledPath($templatePath);
        return file_exists($compiledPath) && !$this->needsRecompile($templatePath, $compiledPath);
    }

    /**
     * Force recompilation of a template
     */
    public function recompile(string $templatePath): string
    {
        $compiledPath = $this->getCompiledPath($templatePath);
        $this->compile($templatePath, $compiledPath);
        return $compiledPath;
    }

    /**
     * Clear all cached templates
     */
    public function clear(): bool
    {
        $success = true;
        $files = glob($this->cacheDir . '/*' . self::CACHE_EXTENSION);

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        // Clear static cache
        self::$lastCheckTimes = [];

        return $success;
    }

    /**
     * Clear cache for specific template
     */
    public function clearTemplate(string $templatePath): bool
    {
        $compiledPath = $this->getCompiledPath($templatePath);

        if (file_exists($compiledPath)) {
            $success = unlink($compiledPath);

            // Clear from static cache
            unset(self::$lastCheckTimes[$compiledPath]);

            return $success;
        }

        return true;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $files = glob($this->cacheDir . '/*' . self::CACHE_EXTENSION);
        $totalSize = 0;
        $oldestFile = time();
        $newestFile = 0;

        if ($files !== false) {
            foreach ($files as $file) {
                $stat = stat($file);
                if ($stat !== false) {
                    $totalSize += $stat['size'];
                    $mtime = $stat['mtime'];
                    $oldestFile = min($oldestFile, $mtime);
                    $newestFile = max($newestFile, $mtime);
                }
            }
        }

        return [
            'cache_dir' => $this->cacheDir,
            'debug_mode' => $this->debug,
            'check_interval' => $this->checkInterval,
            'cached_files_count' => count($files ?: []),
            'total_cache_size_bytes' => $totalSize,
            'total_cache_size_mb' => round($totalSize / 1024 / 1024, 2),
            'oldest_cache_file' => $oldestFile < time() ? date('Y-m-d H:i:s', $oldestFile) : null,
            'newest_cache_file' => $newestFile > 0 ? date('Y-m-d H:i:s', $newestFile) : null,
            'last_check_count' => count(self::$lastCheckTimes),
        ];
    }

    /**
     * Warm up cache by compiling all templates in a directory
     */
    public function warmUp(string $templateDir, bool $force = false): array
    {
        $compiled = [];
        $errors = [];

        if (!is_dir($templateDir)) {
            throw new RuntimeException("Template directory not found: {$templateDir}");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($templateDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $templatePath = $file->getPathname();

                try {
                    if ($force) {
                        $this->recompile($templatePath);
                    } else {
                        $this->get($templatePath);
                    }
                    $compiled[] = $templatePath;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'template' => $templatePath,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return [
            'compiled' => $compiled,
            'errors' => $errors,
            'total' => count($compiled),
            'failed' => count($errors)
        ];
    }

    /**
     * Get cache size information
     */
    public function getSize(): array
    {
        $files = glob($this->cacheDir . '/*' . self::CACHE_EXTENSION);
        $totalSize = 0;
        $fileCount = 0;

        if ($files !== false) {
            $fileCount = count($files);

            foreach ($files as $file) {
                $size = filesize($file);
                if ($size !== false) {
                    $totalSize += $size;
                }
            }
        }

        return [
            'files' => $fileCount,
            'bytes' => $totalSize,
            'kb' => round($totalSize / 1024, 2),
            'mb' => round($totalSize / 1024 / 1024, 2),
        ];
    }

    /**
     * Create cache directory if it doesn't exist
     */
    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                throw new RuntimeException("Cannot create cache directory: {$this->cacheDir}");
            }
        }

        if (!is_writable($this->cacheDir)) {
            throw new RuntimeException("Cache directory is not writable: {$this->cacheDir}");
        }
    }

    /**
     * Generate compiled file path from template path
     */
    private function getCompiledPath(string $templatePath): string
    {
        // Use xxh3 for fast, high-quality hashing
        $hash = hash('xxh3', $templatePath);

        // Include original filename for easier debugging
        $basename = basename($templatePath, '.html');

        // Clean basename for filesystem compatibility
        $cleanBasename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);

        $filename = $cleanBasename . '_' . $hash . self::CACHE_EXTENSION;

        return $this->cacheDir . '/' . $filename;
    }

    /**
     * Check if template needs recompilation
     */
    private function needsRecompile(string $templatePath, string $compiledPath): bool
    {
        // If compiled file doesn't exist, we need to compile
        if (!file_exists($compiledPath)) {
            return true;
        }

        // Get modification times
        $templateMtime = filemtime($templatePath);
        $compiledMtime = filemtime($compiledPath);

        if ($templateMtime === false || $compiledMtime === false) {
            return true;
        }

        // In debug mode, always check for changes
        if ($this->debug) {
            return $templateMtime > $compiledMtime;
        }

        // In production mode, use rate-limited checking
        $now = time();
        $cacheKey = $compiledPath;

        // Check if we've checked this file recently
        if (isset(self::$lastCheckTimes[$cacheKey])) {
            $timeSinceLastCheck = $now - self::$lastCheckTimes[$cacheKey];

            // If we checked recently, assume it's still valid
            if ($timeSinceLastCheck < $this->checkInterval) {
                return false;
            }
        }

        // Update last check time
        self::$lastCheckTimes[$cacheKey] = $now;

        // Prevent memory leak by limiting cache size
        if (count(self::$lastCheckTimes) > 100) {
            self::$lastCheckTimes = array_slice(self::$lastCheckTimes, -50, null, true);
        }

        // Actually check if recompilation is needed
        return $templateMtime > $compiledMtime;
    }

    /**
     * Compile template and write to cache
     */
    private function compile(string $templatePath, string $compiledPath): void
    {
        // Read template content
        $content = file_get_contents($templatePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read template file: {$templatePath}");
        }

        try {
            // Compile template with proper template path for inheritance
            $compiled = $this->compiler->compile($content, $templatePath);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Template compilation failed for '{$templatePath}': {$e->getMessage()}",
                0,
                $e
            );
        }

        // Ensure directory exists
        $dir = dirname($compiledPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Cannot create cache directory: {$dir}");
        }

        // Write compiled template atomically
        $tempFile = $compiledPath . '.tmp.' . uniqid();

        if (file_put_contents($tempFile, $compiled, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write temporary compiled template: {$tempFile}");
        }

        // Atomic move to final location
        if (!rename($tempFile, $compiledPath)) {
            @unlink($tempFile); // Clean up on failure
            throw new RuntimeException("Cannot move compiled template to final location: {$compiledPath}");
        }

        // Set appropriate permissions
        chmod($compiledPath, 0644);

        // Invalidate OPcache for the new file
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($compiledPath, true);
        }
    }
}