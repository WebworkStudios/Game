<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Cache\CacheDriverInterface;
use Framework\Cache\CacheManager;

/**
 * TemplateCache - Vollständige und robuste Implementierung
 *
 * FEATURES:
 * - Template Caching mit Dependency Tracking
 * - Fragment Caching für Widgets
 * - Cache Invalidation bei Template-Änderungen
 * - Multiple Cache Driver Support
 * - Emergency Fallback-Modi
 */
class TemplateCache
{
    private const string CACHE_VERSION = '2.0';

    public function __construct(
        private readonly CacheDriverInterface $cache,
        private readonly bool $enabled = true
    ) {}

    /**
     * Template aus Cache laden
     */
    public function load(string $template): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            return $this->cache->get($this->templateCacheKey($template));
        } catch (\Throwable $e) {
            error_log("Template cache load error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Template in Cache speichern
     */
    public function store(string $template, string $templatePath, array $data, array $dependencies = []): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $cacheData = [
                'version' => self::CACHE_VERSION,
                'compiled_at' => time(),
                'template_path' => $templatePath,
                'dependency_times' => $this->buildDependencyTimes($dependencies),
                'data' => $data
            ];

            $this->cache->put($this->templateCacheKey($template), $cacheData, 3600); // 1 hour TTL
        } catch (\Throwable $e) {
            error_log("Template cache store error: " . $e->getMessage());
            // Continue without caching
        }
    }

    /**
     * Prüft ob Cache valid ist
     */
    public function isValid(string $template, array $dependencies = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $cacheKey = $this->templateCacheKey($template);
            $cached = $this->cache->get($cacheKey);

            if (!$cached || ($cached['version'] ?? '') !== self::CACHE_VERSION) {
                return false;
            }

            // Check dependencies
            foreach ($dependencies as $depPath) {
                if (!file_exists($depPath)) {
                    $this->cache->forget($cacheKey);
                    return false;
                }

                $cachedTime = $cached['dependency_times'][$depPath] ?? 0;
                if (filemtime($depPath) > $cachedTime) {
                    $this->cache->forget($cacheKey);
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            error_log("Template cache validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fragment aus Cache laden
     */
    public function getFragment(string $key): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $cached = $this->cache->get($this->fragmentCacheKey($key));

            if ($cached && ($cached['expires_at'] ?? 0) >= time()) {
                return $cached['content'] ?? null;
            }

            if ($cached) {
                $this->cache->forget($this->fragmentCacheKey($key));
            }

            return null;
        } catch (\Throwable $e) {
            error_log("Fragment cache get error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fragment in Cache speichern
     */
    public function storeFragment(string $key, string $content, int $ttl = 300): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $fragmentData = [
                'content' => $content,
                'expires_at' => time() + $ttl,
                'cached_at' => time()
            ];

            $this->cache->put($this->fragmentCacheKey($key), $fragmentData, $ttl);
        } catch (\Throwable $e) {
            error_log("Fragment cache store error: " . $e->getMessage());
            // Continue without caching
        }
    }

    /**
     * Gesamten Cache leeren
     */
    public function clear(): bool
    {
        try {
            return $this->cache->flush();
        } catch (\Throwable $e) {
            error_log("Cache clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Spezifischen Template-Cache löschen
     */
    public function forget(string $template): bool
    {
        try {
            return $this->cache->forget($this->templateCacheKey($template));
        } catch (\Throwable $e) {
            error_log("Cache forget error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cache-Statistiken abrufen
     */
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'version' => self::CACHE_VERSION,
            'driver' => get_class($this->cache),
            'memory_usage' => memory_get_usage(true),
        ];
    }

    // ===================================================================
    // PRIVATE HELPER METHODS
    // ===================================================================

    private function templateCacheKey(string $template): string
    {
        return 'template_' . md5($template);
    }

    private function fragmentCacheKey(string $key): string
    {
        return 'fragment_' . md5($key);
    }

    private function buildDependencyTimes(array $dependencies): array
    {
        $times = [];
        foreach ($dependencies as $depPath) {
            if (file_exists($depPath)) {
                $times[$depPath] = filemtime($depPath);
            }
        }
        return $times;
    }

    // ===================================================================
    // FACTORY METHODS
    // ===================================================================

    /**
     * Factory Method für automatische Cache-Erstellung
     *
     * ROBUST: Mit mehreren Fallback-Strategien
     */
    public static function create(string $cacheDir, bool $enabled = true): self
    {
        // Sicherstellen dass Verzeichnis existiert
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                throw new \RuntimeException("Cannot create cache directory: {$cacheDir}");
            }
        }

        // Cache Driver mit Fallbacks erstellen
        $cacheDriver = self::createCacheDriver($cacheDir);

        return new self($cacheDriver, $enabled);
    }

    /**
     * Cache Driver mit intelligenten Fallbacks erstellen
     */
    private static function createCacheDriver(string $cacheDir): CacheDriverInterface
    {
        try {
            // Option 1: CacheManager verwenden (falls verfügbar)
            if (class_exists('Framework\Cache\CacheManager')) {
                return CacheManager::createOptimal($cacheDir);
            }
        } catch (\Throwable $e) {
            error_log("CacheManager creation failed: " . $e->getMessage());
        }

        try {
            // Option 2: File Cache Driver direkt
            if (class_exists('Framework\Cache\Drivers\FileCacheDriver')) {
                return new \Framework\Cache\Drivers\FileCacheDriver($cacheDir);
            }
        } catch (\Throwable $e) {
            error_log("FileCacheDriver creation failed: " . $e->getMessage());
        }

        try {
            // Option 3: APCu Cache (falls verfügbar)
            if (function_exists('apcu_fetch') && apcu_enabled()) {
                return new \Framework\Cache\Drivers\ApcuCacheDriver('template_');
            }
        } catch (\Throwable $e) {
            error_log("ApcuCacheDriver creation failed: " . $e->getMessage());
        }

        // Fallback: Dummy Cache Driver (kein Caching, aber funktional)
        return self::createDummyCacheDriver();
    }

    /**
     * Emergency Fallback: Dummy Cache Driver
     */
    private static function createDummyCacheDriver(): CacheDriverInterface
    {
        return new class implements CacheDriverInterface {
            public function get(string $key): mixed
            {
                return null;
            }

            public function put(string $key, mixed $value, int $ttl = 3600): bool
            {
                return true;
            }

            public function forget(string $key): bool
            {
                return true;
            }

            public function flush(): bool
            {
                return true;
            }

            public function exists(string $key): bool
            {
                return false;
            }
        };
    }

    /**
     * Cache mit spezifischem Driver erstellen
     */
    public static function createWithDriver(CacheDriverInterface $driver, bool $enabled = true): self
    {
        return new self($driver, $enabled);
    }

    /**
     * Deaktivierte Cache-Instanz erstellen (für Testing/Debug)
     */
    public static function createDisabled(): self
    {
        return new self(self::createDummyCacheDriver(), false);
    }
}