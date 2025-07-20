<?php

declare(strict_types=1);

namespace Framework\Routing;

use Exception;
use Framework\Core\CacheDriverDetector;
use Framework\Http\HttpMethod;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Router Cache - COMPLETE with ALL original methods + PHP 8.4 iterator_to_array() Optimizations
 *
 * COMPLETE IMPLEMENTATION:
 * ✅ All original public methods preserved
 * ✅ Full backward compatibility
 * ✅ PHP 8.4 optimizations under the hood
 * ✅ Memory-efficient lazy loading
 * ✅ Smart caching with APCu + File fallback
 */
readonly class RouterCache
{
    public function __construct(
        private string $cacheFile,
        private string $actionsPath,
        private bool   $debugMode = false
    )
    {
    }

    // ===================================================================
    // ORIGINAL PUBLIC API (Complete backward compatibility)
    // ===================================================================

    /**
     * Check if cache file exists
     */
    public function exists(): bool
    {
        return file_exists($this->cacheFile);
    }

    /**
     * Get cache file path
     */
    public function getCacheFile(): string
    {
        return $this->cacheFile;
    }

    /**
     * Get actions path
     */
    public function getActionsPath(): string
    {
        return $this->actionsPath;
    }

    /**
     * Check if cache should be rebuilt
     */
    public function shouldRebuild(): bool
    {
        return $this->shouldRebuildCache();
    }

    /**
     * OPTIMIZED: Check if cache should be rebuilt
     */
    private function shouldRebuildCache(): bool
    {
        if (!file_exists($this->cacheFile)) {
            return true;
        }

        $cacheTime = filemtime($this->cacheFile);
        return $this->hasFileChanges($cacheTime);
    }

    /**
     * OPTIMIZED: Lazy file change detection
     */
    private function hasFileChanges(int $cacheTime): bool
    {
        if (!is_dir($this->actionsPath)) {
            return false;
        }

        // Use Generator for memory-efficient file scanning
        $fileScanner = function () {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->actionsPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    yield $file;
                }
            }
        };

        // Early exit on first changed file
        foreach ($fileScanner() as $file) {
            if (filemtime($file->getPathname()) > $cacheTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load cache (alias for get)
     */
    public function load(): ?array
    {
        return $this->get();
    }

    /**
     * Get cached routes and named routes
     */
    public function get(): ?array
    {
        if (!$this->shouldRebuildCache()) {
            $routes = $this->loadFromCache();

            if (!empty($routes)) {
                return [
                    'routes' => $routes,
                    'namedRoutes' => $this->buildNamedRoutesArray($routes),
                ];
            }
        }

        return null;
    }

    /**
     * OPTIMIZED: Load routes from cache with lazy loading
     */
    private function loadFromCache(): array
    {
        // Try APCu cache first (fastest)
        $driver = CacheDriverDetector::detectOptimalDriver();

        if ($driver === 'apcu') {
            $routes = $this->loadFromApcuCache();
            if (!empty($routes)) {
                return $routes;
            }
        }

        // Fallback to file cache
        return $this->loadFromFileCache();
    }

    /**
     * OPTIMIZED: APCu cache loading with compression support
     */
    private function loadFromApcuCache(): array
    {
        $isCompressed = apcu_fetch('kickerscup_routes_is_compressed', $success);

        if ($success && $isCompressed) {
            $compressed = apcu_fetch('kickerscup_routes_compressed', $success);
            if ($success && function_exists('gzuncompress')) {
                $serialized = gzuncompress($compressed);
                $cacheData = unserialize($serialized);

                if (is_array($cacheData)) {
                    return $this->convertCacheDataToRoutes($cacheData);
                }
            }
        } else {
            $cacheData = apcu_fetch('kickerscup_routes', $success);
            if ($success && is_array($cacheData)) {
                return $this->convertCacheDataToRoutes($cacheData);
            }
        }

        return [];
    }

    /**
     * OPTIMIZED: Convert cache data to RouteEntry objects with iterator_to_array()
     */
    private function convertCacheDataToRoutes(array $cacheData): array
    {
        $generator = function () use ($cacheData) {
            foreach ($cacheData as $data) {
                try {
                    yield new RouteEntry(
                        pattern: $data['pattern'],
                        methods: array_map(fn(string $method) => HttpMethod::from($method), $data['methods']),
                        action: $data['action'],
                        middlewares: $data['middlewares'],
                        name: $data['name'],
                        parameters: $data['parameters'],
                    );
                } catch (Throwable $e) {
                    // Skip invalid routes but continue processing
                    continue;
                }
            }
        };

        return iterator_to_array($generator(), preserve_keys: false);
    }

    /**
     * OPTIMIZED: File cache loading
     */
    private function loadFromFileCache(): array
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }

        try {
            $cacheData = require $this->cacheFile;

            if (is_array($cacheData)) {
                return $this->convertCacheDataToRoutes($cacheData);
            }
        } catch (Throwable $e) {
        }

        return [];
    }

    // ===================================================================
    // ROUTE BUILDING SYSTEM (from original RouterCache)
    // ===================================================================

    /**
     * OPTIMIZED: Build named routes array with lazy evaluation
     */
    private function buildNamedRoutesArray(array $routes): array
    {
        $generator = function () use ($routes) {
            foreach ($routes as $route) {
                if ($route->name !== null) {
                    yield $route->name => $route;
                }
            }
        };

        return iterator_to_array($generator(), preserve_keys: true);
    }

    /**
     * Save routes (alias for put)
     */
    public function save(array $routes): bool
    {
        try {
            $this->put(['routes' => $routes]);
            return true;
        } catch (Throwable $e) {
            if ($this->debugMode) {
                error_log("RouterCache: Failed to save routes - " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Save routes to cache
     */
    public function put(array $data): void
    {
        $routes = $data['routes'] ?? [];
        $this->saveToCache($routes);
    }

    /**
     * OPTIMIZED: Save routes to cache with chunked processing
     */
    private function saveToCache(array $routes): void
    {
        // Process routes in chunks for memory efficiency
        $processedRoutes = $this->processRoutesForCaching($routes);

        // Save to multiple cache layers
        $this->saveToFileCache($processedRoutes);
        $this->saveToMemoryCache($processedRoutes);
    }

    // ===================================================================
    // OPTIMIZED: Core Implementation with iterator_to_array()
    // ===================================================================

    /**
     * OPTIMIZED: Process routes for caching with chunked processing
     */
    private function processRoutesForCaching(array $routes): array
    {
        $chunkSize = 100; // Process 100 routes at a time
        $processedRoutes = [];

        $routeChunks = array_chunk($routes, $chunkSize, true);

        foreach ($routeChunks as $chunk) {
            $chunkGenerator = function () use ($chunk) {
                foreach ($chunk as $route) {
                    yield $this->serializeRoute($route);
                }
            };

            $processedChunk = iterator_to_array($chunkGenerator(), preserve_keys: false);
            $processedRoutes = array_merge($processedRoutes, $processedChunk);

            // Optional: Trigger garbage collection after each chunk
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        return $processedRoutes;
    }

    /**
     * Serialize route for caching
     */
    private function serializeRoute(RouteEntry $route): array
    {
        return [
            'pattern' => $route->pattern,
            'methods' => array_map(fn(HttpMethod $method) => $method->value, $route->methods),
            'action' => $route->action,
            'middlewares' => $route->middlewares,
            'name' => $route->name,
            'parameters' => $route->parameters,
        ];
    }

    /**
     * OPTIMIZED: File cache writing with atomic operations
     */
    private function saveToFileCache(array $routes): void
    {
        $cacheDir = dirname($this->cacheFile);

        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                throw new RuntimeException("Cannot create cache directory: {$cacheDir}");
            }
        }

        $tempFile = $this->cacheFile . '.tmp';
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($routes, true) . ";\n";

        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write to cache file: {$tempFile}");
        }

        if (!rename($tempFile, $this->cacheFile)) {
            @unlink($tempFile);
            throw new RuntimeException("Cannot move cache file from {$tempFile} to {$this->cacheFile}");
        }
    }

    /**
     * OPTIMIZED: APCu cache with compression for large route sets
     */
    private function saveToMemoryCache(array $routes): void
    {
        if (!function_exists('apcu_store')) {
            return;
        }

        // Use compression for large route sets
        $serialized = serialize($routes);

        if (strlen($serialized) > 50000 && function_exists('gzcompress')) {
            $compressed = gzcompress($serialized, 6);
            apcu_store('kickerscup_routes_compressed', $compressed, 3600);
            apcu_store('kickerscup_routes_is_compressed', true, 3600);
        } else {
            apcu_store('kickerscup_routes', $routes, 3600);
            apcu_store('kickerscup_routes_is_compressed', false, 3600);
        }
    }

    /**
     * Clear cache
     */
    public function clear(): bool
    {
        $success = true;

        // Clear file cache
        if (file_exists($this->cacheFile)) {
            $success = unlink($this->cacheFile) && $success;
        }

        // Clear APCu cache
        if (function_exists('apcu_delete')) {
            apcu_delete('kickerscup_routes');
            apcu_delete('kickerscup_routes_compressed');
            apcu_delete('kickerscup_routes_is_compressed');
        }

        return $success;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        return $this->getStatistics();
    }

    /**
     * Get comprehensive cache statistics
     */
    public function getStatistics(): array
    {
        return [
            'file_cache' => $this->getFileCacheStats(),
            'memory_cache' => $this->getMemoryCacheStats(),
            'performance' => $this->getPerformanceStats(),
        ];
    }

    /**
     * File cache statistics
     */
    private function getFileCacheStats(): array
    {
        if (!file_exists($this->cacheFile)) {
            return [
                'exists' => false,
                'size' => 0,
                'age' => 0,
            ];
        }

        $size = filesize($this->cacheFile);
        $age = time() - filemtime($this->cacheFile);

        return [
            'exists' => true,
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'age' => $age,
            'age_human' => $this->formatDuration($age),
        ];
    }

    /**
     * Format bytes in human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes) / log(1024));
        return sprintf('%.2f %s', $bytes / (1024 ** $factor), $units[$factor] ?? 'TB');
    }

    /**
     * Format duration in human readable format
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) return "{$seconds}s";
        if ($seconds < 3600) return sprintf('%.1fm', $seconds / 60);
        if ($seconds < 86400) return sprintf('%.1fh', $seconds / 3600);
        return sprintf('%.1fd', $seconds / 86400);
    }

    /**
     * Memory cache statistics
     */
    private function getMemoryCacheStats(): array
    {
        if (!function_exists('apcu_exists')) {
            return ['available' => false];
        }

        $exists = apcu_exists('kickerscup_routes');
        $isCompressed = apcu_exists('kickerscup_routes_is_compressed') &&
            apcu_fetch('kickerscup_routes_is_compressed');

        return [
            'available' => true,
            'exists' => $exists,
            'compressed' => $isCompressed,
            'apcu_info' => function_exists('apcu_cache_info') ? apcu_cache_info() : null,
        ];
    }

    /**
     * Performance metrics
     */
    private function getPerformanceStats(): array
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        return [
            'memory_usage' => $memoryUsage,
            'memory_usage_human' => $this->formatBytes($memoryUsage),
            'peak_memory' => $peakMemory,
            'peak_memory_human' => $this->formatBytes($peakMemory),
            'cache_driver' => CacheDriverDetector::detectOptimalDriver(),
        ];
    }

    // ===================================================================
    // STATISTICS AND MONITORING
    // ===================================================================

    /**
     * Load route entries directly (backward compatibility)
     */
    public function loadRouteEntries(): array
    {
        $cachedData = $this->get();
        if ($cachedData && !empty($cachedData['routes'])) {
            return $cachedData['routes'];
        }

        // No cache exists or empty - build routes
        $routes = $this->buildRoutes();
        if (!empty($routes)) {
            $this->put(['routes' => $routes]);
        }

        return $routes;
    }

    /**
     * Build routes from Action files
     */
    private function buildRoutes(): array
    {
        $routes = [];

        if (!is_dir($this->actionsPath)) {
            return $routes;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->actionsPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $processedFiles = 0;
        foreach ($iterator as $file) {

            if ($file->getExtension() === 'php') {
                $processedFiles++;

                $className = $this->getClassNameFromFile($file->getPathname());


                if ($className) {

                    $this->loadClass($className, $file->getPathname());

                    if (class_exists($className)) {
                        $classRoutes = $this->extractRoutesFromClass($className);
                    }

                    $routes = array_merge($routes, $classRoutes);
                }
            }
        }
        return $routes;
    }

    /**
     * Extract class name from file path (Windows path support)
     */
    private function getClassNameFromFile(string $filepath): ?string
    {
        // Normalize path separators for Windows compatibility
        $normalizedFilePath = str_replace('\\', '/', $filepath);
        $normalizedActionsPath = str_replace('\\', '/', $this->actionsPath);

        $relativePath = str_replace($normalizedActionsPath, '', $normalizedFilePath);

        // Remove leading slash and .php extension
        $relativePath = ltrim($relativePath, '/');
        $relativePath = str_replace('.php', '', $relativePath);

        // Convert path to namespace
        $namespace = str_replace('/', '\\', $relativePath);
        $fullClassName = 'App\\Actions\\' . $namespace;

        return $fullClassName;
    }

    /**
     * Load class from file
     */
    private function loadClass(string $className, string $filepath): void
    {
        if (!class_exists($className)) try {
            require_once $filepath;

        } catch (Throwable $e) {
        }
    }

    // ===================================================================
    // UTILITY METHODS
    // ===================================================================

    /**
     * Extract routes from class using Route attributes
     */
    private function extractRoutesFromClass(string $className): array
    {
        $routes = [];

        try {

            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(Route::class);

            foreach ($attributes as $attribute) {

                // Create route instance
                $routeInstance = $attribute->newInstance();

                // Get route data
                $pattern = $routeInstance->getPattern();
                $methods = $routeInstance->getValidatedMethods();
                $parameters = $routeInstance->getParameters();

                $route = new RouteEntry(
                    pattern: $pattern,
                    methods: $methods,
                    action: $className,
                    middlewares: $routeInstance->middlewares,
                    name: $routeInstance->name,
                    parameters: $parameters
                );

                $routes[] = $route;
            }
        } catch (Exception $e) {
        }

        return $routes;
    }

    /**
     * Store route entries directly (backward compatibility)
     */
    public function storeRouteEntries(array $routes): void
    {
        $this->put(['routes' => $routes]);
    }
}