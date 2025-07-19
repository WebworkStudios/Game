<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Core\CacheDriverDetector;

/**
 * TemplateCache - Erweitert um intelligente Cache-Driver Detection
 *
 * KORREKTE Integration: Bestehende Logik bleibt, intelligentes Caching kommt dazu
 */
class TemplateCache
{
    private const string CACHE_VERSION = '2.0';
    private const string CACHE_EXTENSION = '.php';

    public function __construct(
        private readonly string $cacheDir,
        private readonly bool   $enabled = true
    )
    {
        $this->ensureCacheDirectory();
    }

    /**
     * Lädt geCachte Template-Daten - ERWEITERT um intelligente Detection
     */
    public function load(string $template): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        // NEU: Versuche zuerst APCu Cache
        $driver = CacheDriverDetector::detectOptimalDriver();

        if ($driver === 'apcu') {
            $cacheKey = 'kickerscup_template_' . md5($template);
            $cached = apcu_fetch($cacheKey, $success);
            if ($success && is_array($cached)) {
                return $cached['data'] ?? null;
            }
        }

        // FALLBACK: File-Cache (bestehende Logik unverändert)
        $cacheFile = $this->getCacheFile($template);

        if (!file_exists($cacheFile)) {
            return null;
        }

        try {
            $cached = require $cacheFile;
            return $cached['data'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Speichert Template-Daten in Cache - ERWEITERT um intelligente Detection
     */
    public function store(string $template, string $templatePath, array $data, array $dependencies = []): void
    {
        if (!$this->enabled) {
            return;
        }

        // Build dependency times (bestehende Logik bleibt)
        $dependencyTimes = [];
        foreach ($dependencies as $depPath) {
            if (file_exists($depPath)) {
                $dependencyTimes[$depPath] = filemtime($depPath);
            }
        }

        $cacheData = [
            'version' => self::CACHE_VERSION,
            'compiled_at' => time(),
            'template_path' => $templatePath,
            'dependency_times' => $dependencyTimes,
            'data' => $data
        ];

        // NEU: Intelligente Cache-Driver Detection
        $driver = CacheDriverDetector::detectOptimalDriver();

        // NEU: APCu Cache für ultra-schnelle Template-Zugriffe
        if ($driver === 'apcu') {
            $cacheKey = 'kickerscup_template_' . md5($template);
            apcu_store($cacheKey, $cacheData, 3600); // 1 Stunde
        }

        // BLEIBT: File-Cache als Fallback (bestehende Logik unverändert)
        $cacheFile = $this->getCacheFile($template);
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $content = "<?php\n\nreturn " . var_export($cacheData, true) . ";\n";
        file_put_contents($cacheFile, $content, LOCK_EX);
    }

    /**
     * Cache-Validierung - ERWEITERT um APCu-Check
     */
    public function isValid(string $template, array $dependencies = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // NEU: APCu Cache prüfen
        $driver = CacheDriverDetector::detectOptimalDriver();

        if ($driver === 'apcu') {
            $cacheKey = 'kickerscup_template_' . md5($template);
            $cached = apcu_fetch($cacheKey, $success);

            if ($success && is_array($cached)) {
                // Version check
                if (($cached['version'] ?? '') !== self::CACHE_VERSION) {
                    apcu_delete($cacheKey); // Invalide Version löschen
                    return false;
                }

                // Check dependencies
                foreach ($dependencies as $depPath) {
                    if (!file_exists($depPath)) {
                        apcu_delete($cacheKey); // Dependency fehlt
                        return false;
                    }

                    $cachedTime = $cached['dependency_times'][$depPath] ?? 0;
                    if (filemtime($depPath) > $cachedTime) {
                        apcu_delete($cacheKey); // Dependency geändert
                        return false;
                    }
                }

                return true; // APCu Cache ist gültig
            }
        }

        // FALLBACK: File-Cache Validierung (bestehende Logik unverändert)
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

            // Check dependencies
            foreach ($dependencies as $depPath) {
                if (!file_exists($depPath)) {
                    return false;
                }

                $cachedTime = $cached['dependency_times'][$depPath] ?? 0;
                if (filemtime($depPath) > $cachedTime) {
                    return false;
                }
            }

            return true;

        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Fragment-Cache für Widgets - ERWEITERT um APCu
     */
    public function getFragment(string $key): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        // NEU: APCu Fragment Cache
        $driver = CacheDriverDetector::detectOptimalDriver();

        if ($driver === 'apcu') {
            $cacheKey = 'kickerscup_fragment_' . md5($key);
            $cached = apcu_fetch($cacheKey, $success);

            if ($success && is_array($cached)) {
                // Check TTL
                if (($cached['expires_at'] ?? 0) >= time()) {
                    return $cached['content'] ?? null;
                } else {
                    apcu_delete($cacheKey); // Expired
                }
            }
        }

        // FALLBACK: File Fragment Cache (bestehende Logik)
        $fragmentFile = $this->getFragmentFile($key);

        if (!file_exists($fragmentFile)) {
            return null;
        }

        try {
            $cached = require $fragmentFile;

            // Check TTL
            if (($cached['expires_at'] ?? 0) < time()) {
                unlink($fragmentFile);
                return null;
            }

            return $cached['content'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Fragment speichern - ERWEITERT um APCu
     */
    public function storeFragment(string $key, string $content, int $ttl = 300): void
    {
        if (!$this->enabled) {
            return;
        }

        $expiresAt = time() + $ttl;
        $fragmentData = [
            'content' => $content,
            'expires_at' => $expiresAt,
            'cached_at' => time()
        ];

        // NEU: APCu Fragment Cache
        $driver = CacheDriverDetector::detectOptimalDriver();

        if ($driver === 'apcu') {
            $cacheKey = 'kickerscup_fragment_' . md5($key);
            apcu_store($cacheKey, $fragmentData, $ttl);
        }

        // BLEIBT: File Fragment Cache
        $fragmentFile = $this->getFragmentFile($key);
        $fragmentDir = dirname($fragmentFile);

        if (!is_dir($fragmentDir)) {
            mkdir($fragmentDir, 0755, true);
        }

        $content = "<?php\n\nreturn " . var_export($fragmentData, true) . ";\n";
        file_put_contents($fragmentFile, $content, LOCK_EX);
    }

    // PRIVATE Helper-Methoden bleiben UNVERÄNDERT:

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        // Subdirectories
        $subdirs = ['templates', 'fragments'];
        foreach ($subdirs as $subdir) {
            $path = $this->cacheDir . '/' . $subdir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    private function getCacheFile(string $template): string
    {
        $hash = md5($template);
        return $this->cacheDir . '/templates/' . $hash . self::CACHE_EXTENSION;
    }

    private function getFragmentFile(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . '/fragments/' . $hash . self::CACHE_EXTENSION;
    }
}