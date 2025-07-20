<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Cache\CacheDriverInterface;
use Framework\Cache\CacheManager;
use Framework\Cache\Drivers\NullCacheDriver;

/**
 * TemplateCache - Vollständige und robuste Implementierung
 *
 * GEFIXT: Komplette Cache-Validation gegen weiße Seiten beim Refresh
 *
 * FEATURES:
 * - Template Caching mit Dependency Tracking
 * - Fragment Caching für Widgets
 * - Cache Invalidation bei Template-Änderungen
 * - Multiple Cache Driver Support
 * - Emergency Fallback-Modi
 * - Erweiterte Cache-Integrität-Validation
 * - Cache-Corruption-Recovery
 */
class TemplateCache
{
    private const string CACHE_VERSION = '2.1';  // Erhöht für neue Features
    private const int DEFAULT_TTL = 3600;        // 1 hour
    private const int FRAGMENT_TTL = 300;        // 5 minutes

    public function __construct(
        private readonly CacheDriverInterface $cache,
        private readonly bool $enabled = true
    ) {}

    /**
     * ERWEITERT: Template aus Cache laden mit verbesserter Corruption-Detection
     */
    public function load(string $template): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $cacheKey = $this->templateCacheKey($template);
            $cached = $this->cache->get($cacheKey);

            if ($cached === null) {
                return null;
            }

            // KRITISCH: Erweiterte Cache-Validation
            if (!$this->validateCacheIntegrity($cached)) {
                error_log("Cache corruption detected for template: {$template}");
                $this->safeForgetCache($cacheKey);
                return null;
            }

            return $cached;

        } catch (\Throwable $e) {
            error_log("Template cache load error: " . $e->getMessage());

            // KRITISCH: Cleanup bei Fehlern
            try {
                $this->safeForgetCache($this->templateCacheKey($template));
            } catch (\Throwable $cleanupError) {
                error_log("Cache cleanup error: " . $cleanupError->getMessage());
            }

            return null;
        }
    }

    /**
     * ERWEITERT: Template in Cache speichern mit Integrität-Checks
     */
    public function store(string $template, string $templatePath, array $data, array $dependencies = []): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            // KRITISCH: Data-Validation vor Speicherung
            if (!$this->validateDataForStorage($data)) {
                error_log("Invalid data structure for template cache: {$template}");
                return;
            }

            $cacheData = [
                'version' => self::CACHE_VERSION,
                'compiled_at' => time(),
                'template_path' => $templatePath,
                'dependency_times' => $this->buildDependencyTimes($dependencies),
                'data' => $data,
                'checksum' => $this->calculateDataChecksum($data),  // NEU: Integrity checksum
                'php_version' => PHP_VERSION,                       // NEU: PHP Version tracking
                'template_size' => file_exists($templatePath) ? filesize($templatePath) : 0
            ];

            $this->cache->put($this->templateCacheKey($template), $cacheData, self::DEFAULT_TTL);

        } catch (\Throwable $e) {
            error_log("Template cache store error: " . $e->getMessage());
            // Continue without caching
        }
    }

    /**
     * ERWEITERT: Cache-Validierung mit mehrschichtiger Prüfung
     */
    public function isValid(string $template, array $dependencies = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $cacheKey = $this->templateCacheKey($template);
            $cached = $this->cache->get($cacheKey);

            if (!$cached) {
                return false;
            }

            // Layer 1: Basic structure validation
            if (!$this->validateCacheStructure($cached)) {
                $this->safeForgetCache($cacheKey);
                return false;
            }

            // Layer 2: Version and PHP compatibility check
            if (!$this->validateCacheCompatibility($cached)) {
                $this->safeForgetCache($cacheKey);
                return false;
            }

            // Layer 3: Dependencies check
            if (!$this->validateCacheDependencies($cached, $dependencies)) {
                $this->safeForgetCache($cacheKey);
                return false;
            }

            // Layer 4: Data integrity check
            if (!$this->validateCacheIntegrity($cached)) {
                $this->safeForgetCache($cacheKey);
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            error_log("Template cache validation error: " . $e->getMessage());

            // Bei Validierungsfehlern Cache entfernen
            try {
                $this->safeForgetCache($this->templateCacheKey($template));
            } catch (\Throwable $cleanupError) {
                error_log("Cache cleanup after validation error failed: " . $cleanupError->getMessage());
            }

            return false;
        }
    }

    /**
     * NEU: Cache-Struktur-Validation (Layer 1)
     */
    private function validateCacheStructure(array $cached): bool
    {
        $requiredFields = ['version', 'compiled_at', 'template_path', 'data'];

        foreach ($requiredFields as $field) {
            if (!isset($cached[$field])) {
                return false;
            }
        }

        // Type checks
        if (!is_int($cached['compiled_at']) ||
            !is_string($cached['template_path']) ||
            !is_array($cached['data'])) {
            return false;
        }

        return true;
    }

    /**
     * NEU: Cache-Kompatibilität-Validation (Layer 2)
     */
    private function validateCacheCompatibility(array $cached): bool
    {
        // Version check
        if (($cached['version'] ?? '') !== self::CACHE_VERSION) {
            return false;
        }

        // PHP Version compatibility check
        $cachedPhpVersion = $cached['php_version'] ?? '';
        if (!empty($cachedPhpVersion) && version_compare($cachedPhpVersion, PHP_VERSION, '>')) {
            error_log("Cache created with newer PHP version: {$cachedPhpVersion} vs " . PHP_VERSION);
            return false;
        }

        // Age check (invalidate very old cache)
        $maxAge = 86400; // 24 hours
        if ((time() - ($cached['compiled_at'] ?? 0)) > $maxAge) {
            return false;
        }

        return true;
    }

    /**
     * NEU: Dependencies-Validation (Layer 3)
     */
    private function validateCacheDependencies(array $cached, array $dependencies): bool
    {
        // Template file check
        $templatePath = $cached['template_path'];
        if (!file_exists($templatePath)) {
            return false;
        }

        // Template size check (detect modifications)
        $cachedSize = $cached['template_size'] ?? 0;
        $currentSize = filesize($templatePath);
        if ($cachedSize !== $currentSize) {
            return false;
        }

        // Dependencies timestamp check
        $dependencyTimes = $cached['dependency_times'] ?? [];
        foreach ($dependencies as $depPath) {
            if (!file_exists($depPath)) {
                return false;
            }

            $cachedTime = $dependencyTimes[$depPath] ?? 0;
            if (filemtime($depPath) > $cachedTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * NEU: Cache-Integrität-Validation (Layer 4)
     */
    private function validateCacheIntegrity(array $cached): bool
    {
        $data = $cached['data'] ?? [];

        if (!is_array($data)) {
            return false;
        }

        // Checksum validation
        $storedChecksum = $cached['checksum'] ?? '';
        $currentChecksum = $this->calculateDataChecksum($data);

        if (!empty($storedChecksum) && $storedChecksum !== $currentChecksum) {
            error_log("Cache checksum mismatch: stored={$storedChecksum}, current={$currentChecksum}");
            return false;
        }

        // KRITISCH: Serialized data integrity check
        try {
            if (isset($data['tokens']) && is_array($data['tokens'])) {
                // Quick validation of token structure
                foreach (array_slice($data['tokens'], 0, 5) as $token) {
                    if (!is_array($token) || !isset($token['type'])) {
                        return false;
                    }
                }
            }

            // Test full serialization roundtrip
            $serialized = serialize($data);
            $unserialized = unserialize($serialized);

            if (!is_array($unserialized)) {
                return false;
            }

        } catch (\Throwable $e) {
            error_log("Cache integrity validation error: " . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * NEU: Data-Validation für Storage
     */
    private function validateDataForStorage(array $data): bool
    {
        try {
            // Basic structure check
            if (empty($data)) {
                return false;
            }

            // Serialization test
            $serialized = serialize($data);
            if ($serialized === false) {
                return false;
            }

            $unserialized = unserialize($serialized);
            if (!is_array($unserialized)) {
                return false;
            }

            // Size check (prevent huge cache entries)
            if (strlen($serialized) > 1048576) { // 1MB limit
                error_log("Cache data too large: " . strlen($serialized) . " bytes");
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            error_log("Cache data validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NEU: Checksum für Cache-Integrität
     */
    private function calculateDataChecksum(array $data): string
    {
        return md5(serialize($data));
    }

    /**
     * ERWEITERT: Fragment-Caching mit Validation
     */
    public function getFragment(string $key): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $cacheKey = $this->fragmentCacheKey($key);
            $cached = $this->cache->get($cacheKey);

            if ($cached === null || !is_array($cached)) {
                return null;
            }

            // Fragment validation
            if (!isset($cached['content'], $cached['created_at']) ||
                !is_string($cached['content']) ||
                !is_int($cached['created_at'])) {
                $this->safeForgetCache($cacheKey);
                return null;
            }

            // Age check
            if ((time() - $cached['created_at']) > self::FRAGMENT_TTL) {
                $this->safeForgetCache($cacheKey);
                return null;
            }

            return $cached['content'];

        } catch (\Throwable $e) {
            error_log("Fragment cache get error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ERWEITERT: Fragment speichern mit Metadata
     */
    public function storeFragment(string $key, string $content, int $ttl = self::FRAGMENT_TTL): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $fragmentData = [
                'content' => $content,
                'created_at' => time(),
                'ttl' => $ttl,
                'size' => strlen($content)
            ];

            $this->cache->put($this->fragmentCacheKey($key), $fragmentData, $ttl);

        } catch (\Throwable $e) {
            error_log("Fragment cache store error: " . $e->getMessage());
        }
    }

    /**
     * ERWEITERT: Cache-Eintrag sicher entfernen
     */
    public function forget(string $template): bool
    {
        if (!$this->enabled) {
            return true;
        }

        return $this->safeForgetCache($this->templateCacheKey($template));
    }

    /**
     * ERWEITERT: Kompletten Cache sicher leeren
     */
    public function clear(): bool
    {
        if (!$this->enabled) {
            return true;
        }

        try {
            return $this->cache->flush();
        } catch (\Throwable $e) {
            error_log("Cache clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NEU: Cache-Status und Statistiken
     */
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'version' => self::CACHE_VERSION,
            'php_version' => PHP_VERSION,
            'default_ttl' => self::DEFAULT_TTL,
            'fragment_ttl' => self::FRAGMENT_TTL,
        ];
    }

    /**
     * NEU: Cache-Health-Check
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'ok',
            'issues' => [],
            'cache_enabled' => $this->enabled,
        ];

        if (!$this->enabled) {
            $health['status'] = 'disabled';
            return $health;
        }

        try {
            // Test basic cache operations
            $testKey = 'health_check_' . uniqid();
            $testData = ['test' => true, 'timestamp' => time()];

            $this->cache->put($testKey, $testData, 60);
            $retrieved = $this->cache->get($testKey);
            $this->cache->forget($testKey);

            if ($retrieved !== $testData) {
                $health['status'] = 'error';
                $health['issues'][] = 'Cache read/write test failed';
            }

        } catch (\Throwable $e) {
            $health['status'] = 'error';
            $health['issues'][] = 'Cache driver error: ' . $e->getMessage();
        }

        return $health;
    }

    // ===================================================================
    // PRIVATE HELPER METHODS
    // ===================================================================

    /**
     * SICHER: Cache-Key entfernen mit Error-Handling
     */
    private function safeForgetCache(string $cacheKey): bool
    {
        try {
            return $this->cache->forget($cacheKey);
        } catch (\Throwable $e) {
            error_log("Safe cache forget error: " . $e->getMessage());
            return false;
        }
    }

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
     * NEU: Disabled Cache erstellen (für Fallback)
     */
    public static function createDisabled(): self
    {
        return new self(new NullCacheDriver(), false);
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

        // Fallback: Null Cache Driver (disabled cache)
        return new NullCacheDriver();
    }
}
