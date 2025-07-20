<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Core\CacheDriverDetector;
use Framework\Http\HttpMethod;
use Generator;

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
        private bool $debugMode = false
    ) {}

    // ===================================================================
    // ORIGINAL PUBLIC API (Complete backward compatibility)
    // ===================================================================

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
     * Save routes to cache
     */
    public function put(array $data): void
    {
        $routes = $data['routes'] ?? [];
        $this->saveToCache($routes);
    }

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
     * Load cache (alias for get)
     */
    public function load(): ?array
    {
        return $this->get();
    }

    /**
     * Save routes (alias for put)
     */
    public function save(array $routes): bool
    {
        try {
            $this->put(['routes' => $routes]);
            return true;
        } catch (\Throwable $e) {
            if ($this->debugMode) {
                error_log("RouterCache: Failed to save routes - " . $e->getMessage());
            }
            return false;
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
     * Debug cache information
     */
    public function debug(): array
    {
        $stats = $this->getStatistics();
        $debugInfo = [
            'cache_file' => $this->cacheFile,
            'actions_path' => $this->actionsPath,
            'exists' => $this->exists(),
            'should_rebuild' => $this->shouldRebuildCache(),
            'file_stats' => $stats['file_cache'],
            'memory_stats' => $stats['memory_cache'],
            'performance' => $stats['performance'],
        ];

        if ($this->debugMode) {
            echo "\n=== ROUTER CACHE DEBUG ===\n";
            echo "Cache File: " . $debugInfo['cache_file'] . "\n";
            echo "Actions Path: " . $debugInfo['actions_path'] . "\n";
            echo "Cache Exists: " . ($debugInfo['exists'] ? 'Yes' : 'No') . "\n";
            echo "Should Rebuild: " . ($debugInfo['should_rebuild'] ? 'Yes' : 'No') . "\n";

            if ($debugInfo['exists']) {
                echo "File Size: " . ($debugInfo['file_stats']['size_human'] ?? 'Unknown') . "\n";
                echo "File Age: " . ($debugInfo['file_stats']['age_human'] ?? 'Unknown') . "\n";
            }

            echo "Memory Driver: " . ($debugInfo['performance']['cache_driver'] ?? 'Unknown') . "\n";
            echo "Current Memory: " . ($debugInfo['performance']['memory_usage_human'] ?? 'Unknown') . "\n";
            echo "=========================\n\n";
        }

        return $debugInfo;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        return $this->getStatistics();
    }

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
     * Store route entries directly (backward compatibility)
     */
    public function storeRouteEntries(array $routes): void
    {
        $this->put(['routes' => $routes]);
    }

    // ===================================================================
    // ROUTE BUILDING SYSTEM (from original RouterCache)
    // ===================================================================

    /**
     * Build routes from Action files
     */
    private function buildRoutes(): array
    {
        $routes = [];

        if ($this->debugMode) {
            error_log("=== BUILDING ROUTES ===");
            error_log("Actions path: " . $this->actionsPath);
            error_log("Actions path exists: " . (is_dir($this->actionsPath) ? 'YES' : 'NO'));
        }

        if (!is_dir($this->actionsPath)) {
            if ($this->debugMode) {
                error_log("Actions directory does not exist!");
            }
            return $routes;
        }

        // Show all files in Actions directory
        if ($this->debugMode) {
            $files = scandir($this->actionsPath);
            error_log("Files in actions directory: " . implode(', ', $files));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->actionsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $processedFiles = 0;
        foreach ($iterator as $file) {
            if ($this->debugMode) {
                error_log("Processing file: " . $file->getPathname());
            }

            if ($file->getExtension() === 'php') {
                $processedFiles++;

                if ($this->debugMode) {
                    error_log("  -> PHP file found: " . $file->getFilename());
                }

                $className = $this->getClassNameFromFile($file->getPathname());

                if ($this->debugMode) {
                    error_log("  -> Extracted class name: " . ($className ?? 'NULL'));
                }

                if ($className) {
                    if ($this->debugMode) {
                        error_log("  -> Loading class: " . $className);
                    }

                    $this->loadClass($className, $file->getPathname());

                    if ($this->debugMode) {
                        error_log("  -> Class exists after loading: " . (class_exists($className) ? 'YES' : 'NO'));
                    }

                    if (class_exists($className)) {
                        $classRoutes = $this->extractRoutesFromClass($className);

                        if ($this->debugMode) {
                            error_log("  -> Routes extracted from class: " . count($classRoutes));

                            if (!empty($classRoutes)) {
                                foreach ($classRoutes as $route) {
                                    error_log("    -> Route: " . $route->pattern . " -> " . $route->action);
                                }
                            }
                        }

                        $routes = array_merge($routes, $classRoutes);
                    }
                }
            }
        }

        if ($this->debugMode) {
            error_log("Total PHP files processed: " . $processedFiles);
            error_log("Total routes built: " . count($routes));
        }

        return $routes;
    }

    /**
     * Extract class name from file path (Windows path support)
     */
    private function getClassNameFromFile(string $filepath): ?string
    {
        if ($this->debugMode) {
            error_log("=== EXTRACTING CLASS NAME ===");
            error_log("File path: " . $filepath);
        }

        // Normalize path separators for Windows compatibility
        $normalizedFilePath = str_replace('\\', '/', $filepath);
        $normalizedActionsPath = str_replace('\\', '/', $this->actionsPath);

        if ($this->debugMode) {
            error_log("Normalized file path: " . $normalizedFilePath);
            error_log("Normalized actions path: " . $normalizedActionsPath);
        }

        $relativePath = str_replace($normalizedActionsPath, '', $normalizedFilePath);

        if ($this->debugMode) {
            error_log("Relative path: " . $relativePath);
        }

        // Remove leading slash and .php extension
        $relativePath = ltrim($relativePath, '/');
        $relativePath = str_replace('.php', '', $relativePath);

        if ($this->debugMode) {
            error_log("Cleaned relative path: " . $relativePath);
        }

        // Convert path to namespace
        $namespace = str_replace('/', '\\', $relativePath);
        $fullClassName = 'App\\Actions\\' . $namespace;

        if ($this->debugMode) {
            error_log("Full class name: " . $fullClassName);
        }

        return $fullClassName;
    }

    /**
     * Load class from file
     */
    private function loadClass(string $className, string $filepath): void
    {
        if (!class_exists($className)) {
            try {
                require_once $filepath;

                if ($this->debugMode) {
                    error_log("Successfully loaded: " . $className);
                }
            } catch (\Throwable $e) {
                if ($this->debugMode) {
                    error_log("Failed to load class {$className}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Extract routes from class using Route attributes
     */
    private function extractRoutesFromClass(string $className): array
    {
        $routes = [];

        try {
            if ($this->debugMode) {
                error_log("=== EXTRACTING ROUTES FROM CLASS ===");
                error_log("Class: " . $className);
            }

            $reflection = new \ReflectionClass($className);
            $attributes = $reflection->getAttributes(Route::class);

            if ($this->debugMode) {
                error_log("Route attributes found: " . count($attributes));
            }

            foreach ($attributes as $attribute) {
                if ($this->debugMode) {
                    error_log("Processing route attribute...");
                }

                // Create route instance
                $routeInstance = $attribute->newInstance();

                if ($this->debugMode) {
                    error_log("Route instance created");
                }

                // Get route data
                $pattern = $routeInstance->getPattern();
                $methods = $routeInstance->getValidatedMethods();
                $parameters = $routeInstance->getParameters();

                if ($this->debugMode) {
                    error_log("Route pattern: " . $pattern);
                    error_log("Route methods: " . implode(', ', array_map(fn($m) => $m->value, $methods)));
                    error_log("Route parameters: " . implode(', ', $parameters));
                }

                $route = new RouteEntry(
                    pattern: $pattern,
                    methods: $methods,
                    action: $className,
                    middlewares: $routeInstance->middlewares,
                    name: $routeInstance->name,
                    parameters: $parameters
                );

                if ($this->debugMode) {
                    error_log("Route created: " . $route->pattern . " -> " . $route->action);
                }

                $routes[] = $route;
            }
        } catch (\Exception $e) {
            if ($this->debugMode) {
                error_log("Error extracting routes from class " . $className . ": " . $e->getMessage());
            }
        }

        if ($this->debugMode) {
            error_log("Total routes extracted: " . count($routes));
        }

        return $routes;
    }

    // ===================================================================
    // OPTIMIZED: Core Implementation with iterator_to_array()
    // ===================================================================

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
        $fileScanner = function() {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->actionsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
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
                if ($this->debugMode) {
                    error_log("RouterCache: Detected change in " . $file->getPathname());
                }
                return true;
            }
        }

        return false;
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
        } catch (\Throwable $e) {
            if ($this->debugMode) {
                error_log("RouterCache: Failed to load from file - " . $e->getMessage());
            }
        }

        return [];
    }

    /**
     * OPTIMIZED: Convert cache data to RouteEntry objects with iterator_to_array()
     */
    private function convertCacheDataToRoutes(array $cacheData): array
    {
        $generator = function() use ($cacheData) {
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
                } catch (\Throwable $e) {
                    if ($this->debugMode) {
                        error_log("RouterCache: Failed to convert route - " . $e->getMessage());
                    }
                    // Skip invalid routes but continue processing
                    continue;
                }
            }
        };

        return iterator_to_array($generator(), preserve_keys: false);
    }

    /**
     * OPTIMIZED: Build named routes array with lazy evaluation
     */
    private function buildNamedRoutesArray(array $routes): array
    {
        $generator = function() use ($routes) {
            foreach ($routes as $route) {
                if ($route->name !== null) {
                    yield $route->name => $route;
                }
            }
        };

        return iterator_to_array($generator(), preserve_keys: true);
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

        if ($this->debugMode) {
            $this->logCacheStatistics($processedRoutes);
        }
    }

    /**
     * OPTIMIZED: Process routes for caching with chunked processing
     */
    private function processRoutesForCaching(array $routes): array
    {
        $chunkSize = 100; // Process 100 routes at a time
        $processedRoutes = [];

        $routeChunks = array_chunk($routes, $chunkSize, true);

        foreach ($routeChunks as $chunk) {
            $chunkGenerator = function() use ($chunk) {
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
                throw new \RuntimeException("Cannot create cache directory: {$cacheDir}");
            }
        }

        $tempFile = $this->cacheFile . '.tmp';
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($routes, true) . ";\n";

        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Cannot write to cache file: {$tempFile}");
        }

        if (!rename($tempFile, $this->cacheFile)) {
            @unlink($tempFile);
            throw new \RuntimeException("Cannot move cache file from {$tempFile} to {$this->cacheFile}");
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

    // ===================================================================
    // STATISTICS AND MONITORING
    // ===================================================================

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

    /**
     * Log cache statistics for monitoring
     */
    private function logCacheStatistics(array $routes): void
    {
        $routeCount = count($routes);
        $memoryUsage = memory_get_usage(true);

        error_log(sprintf(
            "RouterCache: Cached %d routes, Memory: %s, Driver: %s",
            $routeCount,
            $this->formatBytes($memoryUsage),
            CacheDriverDetector::detectOptimalDriver()
        ));
    }

    // ===================================================================
    // UTILITY METHODS
    // ===================================================================

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
}