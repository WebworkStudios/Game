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
 * Router Cache - VOLLSTÄNDIG KORRIGIERT
 *
 * FIXES:
 * - String zu HttpMethod Enum Konvertierung
 * - Korrekte Pattern-Generierung aus Route-Pfaden
 * - Parameter-Extraktion aus Pfaden
 * - Robuste Serialization
 * - Emergency Fallbacks bei Cache-Fehlern
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

        if (empty($routes)) {
            return;
        }

        $this->saveToCache($routes);
    }

    /**
     * Check if cache should be rebuilt
     */
    private function shouldRebuildCache(): bool
    {
        if (!$this->exists()) {
            return true;
        }

        $cacheTime = filemtime($this->cacheFile);
        if ($cacheTime === false) {
            return true;
        }

        // Check if Actions directory is newer than cache
        if (is_dir($this->actionsPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->actionsPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php' && $file->getMTime() > $cacheTime) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Load routes from memory and file cache
     */
    private function loadFromCache(): array
    {
        // Try APCu first (fastest)
        $routes = $this->loadFromMemoryCache();
        if (!empty($routes)) {
            return $routes;
        }

        // Fallback to file cache
        $routes = $this->loadFromFileCache();
        if (!empty($routes)) {
            // Warm memory cache for next request
            $this->saveToMemoryCache(array_slice($routes, 0, 100)); // Limit memory usage
        }

        return $routes;
    }

    /**
     * Load from APCu memory cache
     */
    private function loadFromMemoryCache(): array
    {
        if (!function_exists('apcu_enabled') || !apcu_enabled()) {
            return [];
        }

        try {
            $isCompressed = apcu_fetch('kickerscup_routes_is_compressed');

            if ($isCompressed) {
                $compressed = apcu_fetch('kickerscup_routes_compressed');
                if ($compressed !== false) {
                    $routes = unserialize(gzuncompress($compressed));
                    return is_array($routes) ? $routes : [];
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
     * KORRIGIERT: Convert cached data back to RouteEntry objects
     */
    private function convertCacheDataToRoutes(array $cacheData): array
    {
        $routes = [];

        foreach ($cacheData as $data) {
            try {
                // KRITISCHER FIX: String zu HttpMethod Enum Konvertierung
                $methods = [];
                foreach ($data['methods'] as $methodString) {
                    try {
                        $methods[] = HttpMethod::from($methodString);
                    } catch (\ValueError $e) {
                        error_log("Invalid HTTP method '{$methodString}' in cached route");
                        continue; // Skip invalid methods
                    }
                }

                // Skip route if no valid methods
                if (empty($methods)) {
                    continue;
                }

                $routes[] = new RouteEntry(
                    pattern: $data['pattern'],
                    methods: $methods,
                    action: $data['action'],
                    middlewares: $data['middlewares'],
                    name: $data['name'],
                    parameters: $data['parameters'],
                );
            } catch (Throwable $e) {
                error_log("Error creating RouteEntry from cache: " . $e->getMessage());
                // Skip invalid routes but continue processing
                continue;
            }
        }

        return $routes;
    }

    /**
     * Validate cache file can be parsed
     */
    private function validateCacheFile(string $file): bool
    {
        try {
            $content = file_get_contents($file);
            if (empty($content) || !str_contains($content, '<?php')) {
                return false;
            }

            // Try to parse without executing
            $tokens = token_get_all($content);
            return !empty($tokens);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Save to APCu memory cache
     */
    private function saveToMemoryCache(array $routes): void
    {
        if (!function_exists('apcu_enabled') || !apcu_enabled()) {
            return;
        }

        try {
            $serialized = serialize($routes);
            $size = strlen($serialized);

            // Compress if larger than 64KB
            if ($size > 65536) {
                $compressed = gzcompress($serialized, 6);
                if ($compressed !== false && strlen($compressed) < $size * 0.8) {
                    apcu_store('kickerscup_routes_compressed', $compressed, 3600);
                    apcu_store('kickerscup_routes_is_compressed', true, 3600);
                    return;
                }
            }

            apcu_store('kickerscup_routes', $routes, 3600);
            apcu_store('kickerscup_routes_is_compressed', false, 3600);

        } catch (\Throwable $e) {
            error_log("APCu cache save error: " . $e->getMessage());
        }
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
     * KORRIGIERT: Serialize route for caching - FIX für HttpMethod Type Error
     */
    private function serializeRoute(RouteEntry $route): array
    {
        return [
            'pattern' => $route->pattern,
            // KRITISCHER FIX: HttpMethod Enum zu String konvertieren
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
            throw new RuntimeException("Failed to generate cache content: " . $e->getMessage());
        }
    }

    /**
     * KORRIGIERT: Build routes from Action files - FIX für Type Conversion
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
     * VOLLSTÄNDIG KORRIGIERT: Extract routes from class using reflection
     *
     * ALLE FIXES:
     * - String zu HttpMethod Enum Konvertierung
     * - Korrekte Pattern-Generierung
     * - Parameter-Extraktion aus Pfaden
     */
    private function extractRoutesFromClass(string $className): array
    {
        try {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(Route::class);

            $routes = [];
            foreach ($attributes as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();

                // KRITISCHER FIX 1: String zu HttpMethod Enum Konvertierung
                $httpMethods = [];
                foreach ($route->methods as $methodString) {
                    try {
                        $httpMethods[] = HttpMethod::from(strtoupper($methodString));
                    } catch (\ValueError $e) {
                        error_log("Invalid HTTP method '{$methodString}' in route for class {$className}");
                        continue; // Skip invalid methods
                    }
                }

                // Skip route if no valid methods
                if (empty($httpMethods)) {
                    error_log("No valid HTTP methods found for route in class {$className}");
                    continue;
                }

                // KRITISCHER FIX 2: Pattern aus Route-Pfad generieren (nicht Raw-Path verwenden)
                $pattern = $this->generatePatternFromPath($route->path, $route->constraints);

                // KRITISCHER FIX 3: Parameter aus Pfad extrahieren
                $parameters = $this->extractParametersFromPath($route->path);

                $routes[] = new RouteEntry(
                    pattern: $pattern,              // KORRIGIERT: Regex-Pattern statt Raw-Path
                    methods: $httpMethods,          // KORRIGIERT: HttpMethod Enums statt Strings
                    action: $className,
                    middlewares: $route->middlewares,
                    name: $route->name,
                    parameters: $parameters,        // KORRIGIERT: Extrahierte Parameter
                );
            }

            return $routes;
        } catch (Exception $e) {
            error_log("Error extracting routes from {$className}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * NEUE METHODE: Generiert Regex-Pattern aus Route-Pfad
     */
    private function generatePatternFromPath(string $path, array $constraints = []): string
    {
        // Normalisiere den Pfad
        $normalizedPath = trim($path, '/');
        $pattern = $normalizedPath === '' ? '/' : "/{$normalizedPath}";

        // Parameter durch Regex-Captures ersetzen
        preg_match_all('/\{(\w+)}/', $pattern, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $param) {
                $constraint = $constraints[$param] ?? '[^/]+';
                $pattern = str_replace("{{$param}}", "(?P<{$param}>{$constraint})", $pattern);
            }
        }

        // Regex-Delimiter hinzufügen
        return '#^' . $pattern . '$#';
    }

    /**
     * NEUE METHODE: Extrahiert Parameter-Namen aus Route-Pfad
     */
    private function extractParametersFromPath(string $path): array
    {
        preg_match_all('/\{(\w+)}/', $path, $matches);
        return $matches[1] ?? [];
    }
}