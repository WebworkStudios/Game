<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Localization\Translator;
use RuntimeException;

/**
 * Filter Manager - Verwaltet alle Template-Filter mit Lazy Loading und XSS-Schutz
 */
class FilterManager
{
    private array $filters = [];
    private array $lazyFilters = [];

    public function __construct(
        private ?Translator $translator = null
    )
    {
        // Register lazy filter definitions (no instantiation yet)
        $this->registerLazyFilters();
    }

    /**
     * Registriert Lazy Filter Definitionen
     */
    private function registerLazyFilters(): void
    {
        // Text/String Filter
        $this->lazyFilters['upper'] = fn() => fn(string $value) => strtoupper($value);
        $this->lazyFilters['lower'] = fn() => fn(string $value) => strtolower($value);
        $this->lazyFilters['capitalize'] = fn() => fn(string $value) => ucfirst(strtolower($value));
        $this->lazyFilters['truncate'] = fn() => [$this, 'truncateFilter'];
        $this->lazyFilters['default'] = fn() => [$this, 'defaultFilter'];
        $this->lazyFilters['raw'] = fn() => [$this, 'rawFilter'];

        // Number/Format Filter
        $this->lazyFilters['number_format'] = fn() => [$this, 'numberFormatFilter'];
        $this->lazyFilters['currency'] = fn() => [$this, 'currencyFilter'];

        // Date Filter
        $this->lazyFilters['date'] = fn() => [$this, 'dateFilter'];

        // Utility Filter
        $this->lazyFilters['length'] = fn() => [$this, 'lengthFilter'];
        $this->lazyFilters['count'] = fn() => [$this, 'lengthFilter'];
        $this->lazyFilters['plural'] = fn() => [$this, 'pluralFilter'];

        // Advanced Text Filter
        $this->lazyFilters['slug'] = fn() => [$this, 'slugFilter'];
        $this->lazyFilters['nl2br'] = fn() => [$this, 'nl2brFilter'];
        $this->lazyFilters['strip_tags'] = fn() => [$this, 'stripTagsFilter'];

        // Advanced Utility Filter
        $this->lazyFilters['json'] = fn() => [$this, 'jsonFilter'];
        $this->lazyFilters['first'] = fn() => [$this, 'firstFilter'];
        $this->lazyFilters['last'] = fn() => [$this, 'lastFilter'];

        // Translation filters (only if translator is available)
        if ($this->translator !== null) {
            $this->lazyFilters['t'] = fn() => [$this, 'translateFilter'];
            $this->lazyFilters['translate'] = fn() => [$this, 'translateFilter'];
            $this->lazyFilters['tp'] = fn() => [$this, 'translatePluralFilter'];
            $this->lazyFilters['translate_plural'] = fn() => [$this, 'translatePluralFilter'];
        }
    }

    /**
     * Apply filter to value
     */
    public function apply(string $filterName, mixed $value, array $parameters = []): mixed
    {
        // Load filter if not already loaded
        if (!isset($this->filters[$filterName])) {
            $this->loadFilter($filterName);
        }

        if (!isset($this->filters[$filterName])) {
            throw new RuntimeException("Filter '{$filterName}' not found");
        }

        $filter = $this->filters[$filterName];

        // Apply filter
        return $filter($value, ...$parameters);
    }

    /**
     * Load filter lazily
     */
    private function loadFilter(string $filterName): void
    {
        if (!isset($this->lazyFilters[$filterName])) {
            return;
        }

        $factory = $this->lazyFilters[$filterName];
        $this->filters[$filterName] = $factory();
    }

    /**
     * Check if filter exists
     */
    public function has(string $filterName): bool
    {
        return isset($this->filters[$filterName]) || isset($this->lazyFilters[$filterName]);
    }

    /**
     * Register custom filter
     */
    public function register(string $name, callable $filter): void
    {
        $this->filters[$name] = $filter;
    }

    /**
     * Get all available filter names
     */
    public function getFilterNames(): array
    {
        return array_unique(array_merge(
            array_keys($this->filters),
            array_keys($this->lazyFilters)
        ));
    }

    // Filter Implementations

    private function truncateFilter(string $value, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length) . $suffix;
    }

    private function defaultFilter(mixed $value, mixed $default = ''): mixed
    {
        return $value ?? $default;
    }

    private function rawFilter(mixed $value): mixed
    {
        return $value;
    }

    private function currencyFilter(float $value, string $currency = 'EUR', string $locale = 'de_DE'): string
    {
        return $this->numberFormatFilter($value, 2) . ' ' . $currency;
    }

    private function numberFormatFilter(float $value, int|string $decimals = 2, string $decimalPoint = ',', string $thousandsSeparator = '.'): string
    {
        // Convert string to int for decimals parameter
        $decimals = is_string($decimals) ? (int)$decimals : $decimals;

        return number_format($value, $decimals, $decimalPoint, $thousandsSeparator);
    }

    private function dateFilter(mixed $value, string $format = 'Y-m-d H:i:s'): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (is_string($value)) {
            return date($format, strtotime($value));
        }

        if (is_int($value)) {
            return date($format, $value);
        }

        return (string)$value;
    }

    private function lengthFilter(mixed $value): int
    {
        if (is_string($value)) {
            return mb_strlen($value);
        }

        if (is_array($value) || is_countable($value)) {
            return count($value);
        }

        return 0;
    }

    private function pluralFilter(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? $singular : $plural;
    }

    private function slugFilter(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-');
    }

    private function nl2brFilter(string $value): string
    {
        return nl2br($value);
    }

    private function stripTagsFilter(string $value, string $allowedTags = ''): string
    {
        return strip_tags($value, $allowedTags);
    }

    private function jsonFilter(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function firstFilter(mixed $value): mixed
    {
        if (is_array($value)) {
            return reset($value);
        }

        if (is_string($value)) {
            return $value[0] ?? null;
        }

        return null;
    }

    private function lastFilter(mixed $value): mixed
    {
        if (is_array($value)) {
            return end($value);
        }

        if (is_string($value)) {
            return $value[strlen($value) - 1] ?? null;
        }

        return null;
    }

    /**
     * Translation Filter - Uses injected translator
     */
    private function translateFilter(string $key, mixed ...$args): string
    {
        if ($this->translator === null) {
            return $key;
        }

        $parameters = [];
        if (!empty($args) && is_array($args[0])) {
            $parameters = $args[0];
        }

        return $this->translator->translate($key, $parameters);
    }

    /**
     * Translation Plural Filter - Uses injected translator
     */
    private function translatePluralFilter(string $key, int $count, array $parameters = []): string
    {
        if ($this->translator === null) {
            return $key;
        }

        return $this->translator->translatePlural($key, $count, $parameters);
    }
}