<?php


declare(strict_types=1);

namespace Framework\Localization;

use InvalidArgumentException;
use RuntimeException;

/**
 * Core Translator Service
 */
class Translator
{
    private const string DEFAULT_LOCALE = 'de';
    private const array SUPPORTED_LOCALES = ['de', 'en', 'fr', 'es'];

    private string $currentLocale = self::DEFAULT_LOCALE;
    private string $fallbackLocale = self::DEFAULT_LOCALE;

    /** @var array<string, array<string, mixed>> */
    private array $translations = [];

    /** @var array<string, bool> */
    private array $loadedNamespaces = [];

    public function __construct(
        private readonly string $languagesPath
    )
    {
        if (!is_dir($this->languagesPath)) {
            throw new InvalidArgumentException("Languages directory does not exist: {$this->languagesPath}");
        }
    }

    /**
     * Set current locale
     */
    public function setLocale(string $locale): self
    {
        if (!$this->isValidLocale($locale)) {
            throw new InvalidArgumentException("Unsupported locale: {$locale}");
        }

        $this->currentLocale = $locale;
        return $this;
    }

    /**
     * Get current locale
     */
    public function getLocale(): string
    {
        return $this->currentLocale;
    }

    /**
     * Set fallback locale
     */
    public function setFallbackLocale(string $locale): self
    {
        if (!$this->isValidLocale($locale)) {
            throw new InvalidArgumentException("Unsupported fallback locale: {$locale}");
        }

        $this->fallbackLocale = $locale;
        return $this;
    }

    /**
     * Translate a key
     */
    public function translate(string $key, array $parameters = []): string
    {
        $translation = $this->getTranslation($key);

        if ($translation === null) {
            return $key; // Return key if no translation found
        }

        return $this->interpolate($translation, $parameters);
    }

    /**
     * Alias for translate (shorter syntax)
     */
    public function t(string $key, array $parameters = []): string
    {
        return $this->translate($key, $parameters);
    }

    /**
     * Translate with pluralization
     */
    public function translatePlural(string $key, int $count, array $parameters = []): string
    {
        $pluralKey = $count === 1 ? "{$key}.singular" : "{$key}.plural";

        $translation = $this->getTranslation($pluralKey);

        if ($translation === null) {
            // Fallback to regular translation
            $translation = $this->getTranslation($key);
            if ($translation === null) {
                return $key;
            }
        }

        $parameters['count'] = $count;
        return $this->interpolate($translation, $parameters);
    }

    /**
     * Check if translation exists
     */
    public function has(string $key): bool
    {
        return $this->getTranslation($key) !== null;
    }

    /**
     * Get all supported locales
     */
    public function getSupportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    /**
     * Load translations for namespace
     */
    public function loadNamespace(string $namespace): void
    {
        $cacheKey = "{$this->currentLocale}.{$namespace}";

        if (isset($this->loadedNamespaces[$cacheKey])) {
            return; // Already loaded
        }

        $translations = $this->loadTranslationFile($this->currentLocale, $namespace);

        if (empty($translations) && $this->currentLocale !== $this->fallbackLocale) {
            // Load fallback translations
            $translations = $this->loadTranslationFile($this->fallbackLocale, $namespace);
        }

        if (!empty($translations)) {
            $this->translations[$cacheKey] = $translations;
        }

        $this->loadedNamespaces[$cacheKey] = true;
    }

    /**
     * Get translation for key
     */
    private function getTranslation(string $key): ?string
    {
        [$namespace, $translationKey] = $this->parseKey($key);

        // Ensure namespace is loaded
        $this->loadNamespace($namespace);

        $cacheKey = "{$this->currentLocale}.{$namespace}";

        // Try current locale
        $translation = $this->getNestedValue($this->translations[$cacheKey] ?? [], $translationKey);

        if ($translation !== null) {
            return $translation;
        }

        // Try fallback locale
        if ($this->currentLocale !== $this->fallbackLocale) {
            $fallbackCacheKey = "{$this->fallbackLocale}.{$namespace}";

            if (!isset($this->loadedNamespaces[$fallbackCacheKey])) {
                $fallbackTranslations = $this->loadTranslationFile($this->fallbackLocale, $namespace);
                if (!empty($fallbackTranslations)) {
                    $this->translations[$fallbackCacheKey] = $fallbackTranslations;
                }
                $this->loadedNamespaces[$fallbackCacheKey] = true;
            }

            return $this->getNestedValue($this->translations[$fallbackCacheKey] ?? [], $translationKey);
        }

        return null;
    }

    /**
     * Parse translation key
     */
    private function parseKey(string $key): array
    {
        if (str_contains($key, '.')) {
            $parts = explode('.', $key, 2);
            return [$parts[0], $parts[1]];
        }

        return ['common', $key];
    }

    /**
     * Get nested value from array using dot notation
     */
    private function getNestedValue(array $array, string $key): ?string
    {
        $keys = explode('.', $key);
        $current = $array;

        foreach ($keys as $k) {
            if (!is_array($current) || !isset($current[$k])) {
                return null;
            }
            $current = $current[$k];
        }

        return is_string($current) ? $current : null;
    }

    /**
     * Load translation file
     */
    private function loadTranslationFile(string $locale, string $namespace): array
    {
        $filePath = "{$this->languagesPath}/{$locale}/{$namespace}.php";

        if (!file_exists($filePath)) {
            return [];
        }

        try {
            $translations = require $filePath;

            if (!is_array($translations)) {
                throw new RuntimeException("Translation file must return array: {$filePath}");
            }

            return $translations;

        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to load translation file {$filePath}: " . $e->getMessage());
        }
    }

    /**
     * Interpolate parameters into translation
     */
    private function interpolate(string $translation, array $parameters): string
    {
        if (empty($parameters)) {
            return $translation;
        }

        $replacements = [];
        foreach ($parameters as $key => $value) {
            $replacements["{{$key}}"] = (string)$value;
            $replacements["%{$key}%"] = (string)$value; // Alternative syntax
        }

        return strtr($translation, $replacements);
    }

    /**
     * Check if locale is valid
     */
    private function isValidLocale(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true);
    }

    /**
     * Clear translation cache
     */
    public function clearCache(): void
    {
        $this->translations = [];
        $this->loadedNamespaces = [];
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'current_locale' => $this->currentLocale,
            'fallback_locale' => $this->fallbackLocale,
            'loaded_namespaces' => count($this->loadedNamespaces),
            'cached_translations' => count($this->translations),
            'supported_locales' => self::SUPPORTED_LOCALES,
        ];
    }
}