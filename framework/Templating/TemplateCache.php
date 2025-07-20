<?php
declare(strict_types=1);
namespace Framework\Templating;

use Framework\Cache\CacheDriverInterface;
use Framework\Cache\CacheManager;

/**
 * REFACTORED TemplateCache - Nutzt das neue Cache-System
 */
class TemplateCache
{
    private const string CACHE_VERSION = '2.0';

    public function __construct(
        private readonly CacheDriverInterface $cache,
        private readonly bool $enabled = true
    ) {}

    /**
     * VEREINFACHT: Single method für Cache-Loading
     */
    public function load(string $template): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        return $this->cache->get($this->templateCacheKey($template));
    }

    /**
     * VEREINFACHT: Single method für Cache-Storing
     */
    public function store(string $template, string $templatePath, array $data, array $dependencies = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $cacheData = [
            'version' => self::CACHE_VERSION,
            'compiled_at' => time(),
            'template_path' => $templatePath,
            'dependency_times' => $this->buildDependencyTimes($dependencies),
            'data' => $data
        ];

        $this->cache->put($this->templateCacheKey($template), $cacheData);
    }

    /**
     * VEREINFACHT: Single method für Cache-Validierung
     */
    public function isValid(string $template, array $dependencies = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

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
    }

    /**
     * VEREINFACHT: Fragment-Cache
     */
    public function getFragment(string $key): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $cached = $this->cache->get($this->fragmentCacheKey($key));

        if ($cached && ($cached['expires_at'] ?? 0) >= time()) {
            return $cached['content'] ?? null;
        }

        if ($cached) {
            $this->cache->forget($this->fragmentCacheKey($key));
        }

        return null;
    }

    /**
     * VEREINFACHT: Fragment speichern
     */
    public function storeFragment(string $key, string $content, int $ttl = 300): void
    {
        if (!$this->enabled) {
            return;
        }

        $fragmentData = [
            'content' => $content,
            'expires_at' => time() + $ttl,
            'cached_at' => time()
        ];

        $this->cache->put($this->fragmentCacheKey($key), $fragmentData, $ttl);
    }

    /**
     * Cache leeren
     */
    public function clear(): bool
    {
        return $this->cache->flush();
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

    /**
     * Factory Method für automatische Cache-Erstellung
     */
    public static function create(string $cacheDir, bool $enabled = true): self
    {
        $cacheManager = CacheManager::createOptimal($cacheDir);
        return new self($cacheManager, $enabled);
    }
}
