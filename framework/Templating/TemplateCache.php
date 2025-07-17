<?php
namespace Framework\Templating;

/**
 * TemplateCache - Erweitert für neue Token-Architektur
 */
class TemplateCache
{
    private const string CACHE_VERSION = '2.0';
    private const string CACHE_EXTENSION = '.php';

    public function __construct(
        private readonly string $cacheDir,
        private readonly bool $enabled = true
    ) {
        $this->ensureCacheDirectory();
    }

    /**
     * Prüft ob Cache gültig ist
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
     * Lädt geCachte Template-Daten
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
            return $cached['data'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Speichert Template-Daten in Cache
     */
    public function store(string $template, string $templatePath, array $data, array $dependencies = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $cacheFile = $this->getCacheFile($template);
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Build dependency times
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

        $content = "<?php\n\nreturn " . var_export($cacheData, true) . ";\n";
        file_put_contents($cacheFile, $content, LOCK_EX);
    }

    /**
     * Fragment-Cache für Widgets
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
     * Speichert Fragment-Cache
     */
    public function storeFragment(string $key, string $content, int $ttl = 300): void
    {
        if (!$this->enabled) {
            return;
        }

        $fragmentFile = $this->getFragmentFile($key);
        $fragmentDir = dirname($fragmentFile);

        if (!is_dir($fragmentDir)) {
            mkdir($fragmentDir, 0755, true);
        }

        $cacheData = [
            'content' => $content,
            'created_at' => time(),
            'expires_at' => time() + $ttl
        ];

        $cacheContent = "<?php\n\nreturn " . var_export($cacheData, true) . ";\n";
        file_put_contents($fragmentFile, $cacheContent, LOCK_EX);
    }

    /**
     * Cache leeren
     */
    public function clear(): void
    {
        $this->clearDirectory($this->cacheDir . '/templates');
        $this->clearDirectory($this->cacheDir . '/fragments');
    }

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

    private function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*' . self::CACHE_EXTENSION);
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}