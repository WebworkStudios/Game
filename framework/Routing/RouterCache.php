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
 * Router Cache - GEFIXT mit robuster Serialization
 *
 * FIXES:
 * - Serialization statt var_export für komplexe Route-Objekte
 * - Robuste Cache-Validierung
 * - Emergency Fallbacks bei Cache-Fehlern
 * - Atomic File Operations
 */
readonly class RouterCache
{
    public function __construct(
        private string $cacheFile,
        private string $actionsPath
    ) {
    }

    /**
     * Check if cache file exists
     */
    public function exists(): bool
    {
        return file_exists($this->cacheFile);
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
     * Load route entries directly (backward compatibility)
     * GEFIXT: Mit Emergency Fallback
     */
    public function loadRouteEntries(): array
    {
        try {
            $cachedData = $this->get();
            if ($cachedData && !empty($cachedData['routes'])) {
                return $cachedData['routes'];
            }
        } catch (\Throwable $e) {
            error_log("Cache loading failed, rebuilding: " . $e->getMessage());
            // Cache löschen bei Fehler
            $this->clear();
        }

        // Fallback: Routes neu aufbauen
        $routes = $this->buildRoutes();
        if (!empty($routes)) {
            try {
                $this->put(['routes' => $routes]);
            } catch (\Throwable $e) {
                error_log("Cache saving failed: " . $e->getMessage());
                // Weitermachen ohne Cache
            }
        }

        return $routes;
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
     * Store routes in cache
     */
    public function put(array $data): void
    {
        $routes = $data['routes'] ?? [];
        $this->saveToCache($routes);
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
     * OPTIMIZED: Load from cache with fallback chain
     */
    private function loadFromCache(): array
    {
        // Try APCu first for speed
        $routes = $this->loadFromMemoryCache();
        if (!empty($routes)) {
            return $routes;
        }

        // Fallback to file cache
        return $this->loadFromFileCache();
    }

    /**
     * OPTIMIZED: APCu cache loading with compression support
     */
    private function loadFromMemoryCache(): array
    {
        if (!function_exists('apcu_fetch')) {
            return [];
        }

        try {
            $isCompressed = apcu_fetch('kickerscup_routes_is_compressed');

            if ($isCompressed) {
                $compressed = apcu_fetch('kickerscup_routes_compressed');
                if ($compressed && function_exists('gzuncompress')) {
                    $serialized = gzuncompress($compressed);
                    return unserialize($serialized) ?: [];
                }
            } else {
                $routes = apcu_fetch('kickerscup_routes');
                return is_array($routes) ? $routes : [];
            }
        } catch (\Throwable $e) {
            error_log("APCu cache loading error: " . $e->getMessage());
        }

        return [];
    }

    /**
     * OPTIMIZED: File cache loading with validation
     */
    private function loadFromFileCache(): array
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }

        try {
            // Verify file integrity before loading
            if (!$this->validateCacheFile($this->cacheFile)) {
                error_log("Corrupted cache file detected, rebuilding...");
                @unlink($this->cacheFile);
                return [];
            }

            $cacheData = require $this->cacheFile;

            if (is_array($cacheData)) {
                return $this->convertCacheDataToRoutes($cacheData);
            }

        } catch (\Throwable $e) {
            error_log("Cache loading error: " . $e->getMessage());

            // Clean up corrupted cache
            @unlink($this->cacheFile);
        }

        return [];
    }

    /**
     * Convert cached data back to RouteEntry objects
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
     * GEFIXT: Serialization statt var_export
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

        try {
            // KRITISCHER FIX: Robuste Content-Generierung
            $content = $this->generateCacheFileContent($routes);

            // CRITICAL: Validate content before writing
            if (empty($content) || !str_contains($content, 'return')) {
                throw new RuntimeException("Invalid cache content generated");
            }

            // ATOMIC: Write to temp file first
            if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
                throw new RuntimeException("Cannot write to cache file: {$tempFile}");
            }

            // VALIDATION: Verify the written file can be parsed
            if (!$this->validateCacheFile($tempFile)) {
                @unlink($tempFile);
                throw new RuntimeException("Generated cache file is invalid");
            }

            // ATOMIC: Move temp file to final location
            if (!rename($tempFile, $this->cacheFile)) {
                @unlink($tempFile);
                throw new RuntimeException("Cannot move cache file from {$tempFile} to {$this->cacheFile}");
            }

        } catch (\Throwable $e) {
            error_log("File cache save error: " . $e->getMessage());
            @unlink($tempFile);
            throw $e;
        }
    }

    /**
     * KRITISCHER FIX: Robuste Cache-Content-Generierung mit Serialization
     */
    private function generateCacheFileContent(array $routes): string
    {
        try {
            // Ensure routes array is valid
            if (!is_array($routes)) {
                $routes = [];
            }

            // EINFACHER FIX: Serialization statt var_export
            // var_export() kann bei komplexen Objekten fehlschlagen
            $serialized = serialize($routes);
            $escapedSerialized = var_export($serialized, true);

            // Build robust content with proper PHP syntax
            return <<<PHP
<?php

declare(strict_types=1);

// Route cache generated at: {date('Y-m-d H:i:s')}
// Framework: KickersCup Manager
// DO NOT EDIT - This file is auto-generated

return unserialize({$escapedSerialized});

PHP;

        } catch (\Throwable $e) {
            error_log("Cache content generation error: " . $e->getMessage());

            // Emergency fallback: empty array
            return <<<PHP
<?php
declare(strict_types=1);
// Emergency fallback - cache generation failed
return [];
PHP;
        }
    }

    /**
     * NEW: Validate cache file can be properly parsed
     */
    private function validateCacheFile(string $filePath): bool
    {
        try {
            // Attempt to include and validate
            $result = require $filePath;

            // Must return an array
            if (!is_array($result)) {
                error_log("Cache file does not return array: {$filePath}");
                return false;
            }

            // Test serialization roundtrip for data integrity
            $testSerialized = serialize($result);
            $testUnserialized = unserialize($testSerialized);

            if (!is_array($testUnserialized)) {
                error_log("Cache data is not serializable: {$filePath}");
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            error_log("Cache file validation failed for {$filePath}: " . $e->getMessage());
            return false;
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

        try {
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
        } catch (\Throwable $e) {
            error_log("APCu cache save error: " . $e->getMessage());
        }
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

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname());

                if ($className) {
                    $this->loadClass($className, $file->getPathname());

                    if (class_exists($className)) {
                        $classRoutes = $this->extractRoutesFromClass($className);
                        $routes = array_merge($routes, $classRoutes);
                    }
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
        if (!class_exists($className)) {
            require_once $filepath;
        }
    }

    /**
     * Extract routes from class using reflection
     */
    private function extractRoutesFromClass(string $className): array
    {
        try {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(Route::class);

            $routes = [];
            foreach ($attributes as $attribute) {
                $route = $attribute->newInstance();
                $routes[] = new RouteEntry(
                    pattern: $route->path,
                    methods: $route->methods,
                    action: $className,
                    middlewares: $route->middlewares,
                    name: $route->name,
                    parameters: []
                );
            }

            return $routes;
        } catch (Exception $e) {
            error_log("Error extracting routes from {$className}: " . $e->getMessage());
            return [];
        }
    }
}