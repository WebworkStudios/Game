<?php
declare(strict_types=1);

namespace Framework\Localization;

use Framework\Database\ConnectionPool;
use Framework\Core\Logger;
use Framework\Core\SessionManagerInterface;

class LocalizationService
{
    private ConnectionPool $db;
    private Logger $logger;
    private SessionManagerInterface $session;
    private array $config;

    // Memory cache for loaded translations
    private array $memoryCache = [];
    private array $loadedCategories = [];

    // Current locale with automatic fallback
    public string $currentLocale {
        get {
            if (!isset($this->currentLocaleValue)) {
                $this->currentLocaleValue = $this->detectLocale();
            }
            return $this->currentLocaleValue;
        }
        set(string $value) {
            if (!in_array($value, $this->config['supported_locales'])) {
                throw new \InvalidArgumentException("Unsupported locale: {$value}");
            }
            $this->currentLocaleValue = $value;
            $this->clearMemoryCache();
        }
    }
    private ?string $currentLocaleValue = null;

    // Fallback locale
    public string $fallbackLocale {
        get => $this->config['fallback_locale'];
    }

    // Supported locales
    public array $supportedLocales {
        get => $this->config['supported_locales'];
    }

    // Performance statistics
    public array $stats {
        get => [
            'memory_cache_size' => count($this->memoryCache),
            'loaded_categories' => count($this->loadedCategories),
            'current_locale' => $this->currentLocale,
            'memory_usage' => memory_get_usage(true)
        ];
    }

    public function __construct(ConnectionPool $db, Logger $logger, array $config, SessionManagerInterface $session)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
        $this->session = $session;
    }

    /**
     * Get translation with fallback chain
     */
    public function get(string $key, array $params = [], ?string $locale = null): string
    {
        $locale = $locale ?: $this->currentLocale;
        $category = $this->extractCategory($key);

        // Try memory cache first
        if ($translation = $this->getFromMemoryCache($key, $locale)) {
            return $this->processParams($translation, $params);
        }

        // Load category if not loaded
        if (!$this->isCategoryLoaded($category, $locale)) {
            $this->loadCategory($category, $locale);
        }

        // Try memory cache again after loading
        if ($translation = $this->getFromMemoryCache($key, $locale)) {
            return $this->processParams($translation, $params);
        }

        // Try fallback locale
        if ($locale !== $this->fallbackLocale) {
            return $this->get($key, $params, $this->fallbackLocale);
        }

        // Return key as fallback
        $this->logger->warning("Translation missing: {$key}", ['locale' => $locale]);
        return $key;
    }

    /**
     * Set translation
     */
    public function set(string $key, string $value, ?string $locale = null): bool
    {
        $locale = $locale ?: $this->currentLocale;
        $category = $this->extractCategory($key);

        try {
            // Insert or update translation
            $existing = $this->db->table('translations')
                ->where('key', $key)
                ->where('locale', $locale)
                ->first();

            if ($existing) {
                $this->db->writeTable('translations')
                    ->where('id', $existing['id'])
                    ->update([
                        'value' => $value,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                $this->db->writeTable('translations')->insert([
                    'key' => $key,
                    'locale' => $locale,
                    'value' => $value,
                    'category' => $category,
                    'is_active' => 1
                ]);
            }

            // Invalidate caches
            $this->invalidateCache($category, $locale);

            return true;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to set translation: {$e->getMessage()}", [
                'key' => $key,
                'locale' => $locale
            ]);
            return false;
        }
    }

    /**
     * Load translations for a category with smart caching
     */
    private function loadCategory(string $category, string $locale): void
    {
        $cacheKey = "{$locale}:{$category}";

        // Try database cache first
        if ($this->config['cache_enabled']) {
            if ($cached = $this->getFromDatabaseCache($category, $locale)) {
                $this->memoryCache[$cacheKey] = $cached;
                $this->loadedCategories[$cacheKey] = true;
                return;
            }
        }

        // Load from database
        $translations = $this->loadFromDatabase($category, $locale);

        // Store in memory cache
        $this->memoryCache[$cacheKey] = $translations;
        $this->loadedCategories[$cacheKey] = true;

        // Store in database cache
        if ($this->config['cache_enabled']) {
            $this->storeDatabaseCache($category, $locale, $translations);
        }
    }

    /**
     * Load translations from database
     */
    private function loadFromDatabase(string $category, string $locale): array
    {
        try {
            $results = $this->db->table('translations')
                ->select(['key', 'value'])
                ->where('locale', $locale)
                ->where('category', $category)
                ->where('is_active', '=', 1)
                ->get();

            $translations = [];
            foreach ($results as $row) {
                $translations[$row['key']] = $row['value'];
            }

            return $translations;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to load translations from database", [
                'category' => $category,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get from database cache
     */
    private function getFromDatabaseCache(string $category, string $locale): ?array
    {
        try {
            $cached = $this->db->table('translation_cache')
                ->where('locale', $locale)
                ->where('category', $category)
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->first();

            if ($cached) {
                return json_decode($cached['translations_json'], true);
            }

        } catch (\Throwable $e) {
            $this->logger->warning("Database cache read failed", [
                'category' => $category,
                'locale' => $locale
            ]);
        }

        return null;
    }

    /**
     * Store in database cache
     */
    private function storeDatabaseCache(string $category, string $locale, array $translations): void
    {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + $this->config['cache_ttl']);

            $this->db->writeTable('translation_cache')
                ->where('locale', $locale)
                ->where('category', $category)
                ->delete();

            $this->db->writeTable('translation_cache')->insert([
                'locale' => $locale,
                'category' => $category,
                'translations_json' => json_encode($translations),
                'expires_at' => $expiresAt
            ]);

        } catch (\Throwable $e) {
            $this->logger->warning("Database cache write failed", [
                'category' => $category,
                'locale' => $locale
            ]);
        }
    }

    /**
     * Detect current locale from various sources
     */
    private function detectLocale(): string
    {
        // 1. URL parameter
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this->supportedLocales)) {
            return $_GET['lang'];
        }

        // 2. Session - using our SessionManager
        $sessionLocale = $this->session->get('locale');
        if ($sessionLocale && in_array($sessionLocale, $this->supportedLocales)) {
            return $sessionLocale;
        }

        // 3. Accept-Language header
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $acceptedLocales = $this->parseAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($acceptedLocales as $locale) {
                if (in_array($locale, $this->supportedLocales)) {
                    return $locale;
                }
            }
        }

        // 4. Fallback
        return $this->fallbackLocale;
    }

    /**
     * Helper methods
     */
    private function extractCategory(string $key): string
    {
        $parts = explode('.', $key, 2);
        return count($parts) > 1 ? $parts[0] : 'general';
    }

    private function getFromMemoryCache(string $key, string $locale): ?string
    {
        $category = $this->extractCategory($key);
        $cacheKey = "{$locale}:{$category}";

        return $this->memoryCache[$cacheKey][$key] ?? null;
    }

    private function isCategoryLoaded(string $category, string $locale): bool
    {
        return isset($this->loadedCategories["{$locale}:{$category}"]);
    }

    private function processParams(string $translation, array $params): string
    {
        if (empty($params)) {
            return $translation;
        }

        foreach ($params as $key => $value) {
            $translation = str_replace(":{$key}", (string)$value, $translation);
        }

        return $translation;
    }

    private function parseAcceptLanguage(string $acceptLanguage): array
    {
        $locales = [];
        $parts = explode(',', $acceptLanguage);

        foreach ($parts as $part) {
            $locale = trim(explode(';', $part)[0]);
            if (strlen($locale) >= 2) {
                $locales[] = substr($locale, 0, 2);
            }
        }

        return array_unique($locales);
    }

    private function invalidateCache(string $category, string $locale): void
    {
        // Clear memory cache
        unset($this->memoryCache["{$locale}:{$category}"]);
        unset($this->loadedCategories["{$locale}:{$category}"]);

        // Clear database cache
        try {
            $this->db->writeTable('translation_cache')
                ->where('locale', $locale)
                ->where('category', $category)
                ->delete();
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to clear database cache", [
                'category' => $category,
                'locale' => $locale
            ]);
        }
    }

    private function clearMemoryCache(): void
    {
        $this->memoryCache = [];
        $this->loadedCategories = [];
    }

    /**
     * Preload critical translations for performance
     */
    public function preload(array $categories = ['general', 'validation', 'auth']): void
    {
        foreach ($categories as $category) {
            $this->loadCategory($category, $this->currentLocale);

            // Also preload fallback locale for critical categories
            if ($this->currentLocale !== $this->fallbackLocale) {
                $this->loadCategory($category, $this->fallbackLocale);
            }
        }
    }

    /**
     * Bulk import translations
     */
    public function import(array $translations, string $locale, bool $overwrite = false): int
    {
        $imported = 0;

        try {
            $this->db->transaction(function() use ($translations, $locale, $overwrite, &$imported) {
                foreach ($translations as $key => $value) {
                    if (!$overwrite) {
                        $exists = $this->db->table('translations')
                            ->where('key', $key)
                            ->where('locale', $locale)
                            ->first();

                        if ($exists) {
                            continue;
                        }
                    }

                    if ($this->set($key, $value, $locale)) {
                        $imported++;
                    }
                }
            });

        } catch (\Throwable $e) {
            $this->logger->error("Bulk import failed: {$e->getMessage()}");
        }

        return $imported;
    }
}