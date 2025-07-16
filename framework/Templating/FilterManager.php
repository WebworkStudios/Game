<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Localization\Translator;
use RuntimeException;

/**
 * Filter Manager - Verwaltet alle Template-Filter mit Lazy Loading und XSS-Schutz
 *
 * KORRIGIERTE VERSION: Sichere Behandlung von null-Werten
 */
class FilterManager
{
    private array $filters = [];
    private array $lazyFilters = [];

    public function __construct(
        private ?Translator $translator = null
    ) {
        // Register lazy filter definitions (no instantiation yet)
        $this->registerLazyFilters();
    }

    /**
     * Registriert Lazy Filter Definitionen - MIT NULL-SICHERHEIT
     */
    private function registerLazyFilters(): void
    {
        // Text/String Filter - NULL-SAFE
        $this->lazyFilters['upper'] = fn() => fn(mixed $value) => $value === null ? '' : strtoupper((string)$value);
        $this->lazyFilters['lower'] = fn() => fn(mixed $value) => $value === null ? '' : strtolower((string)$value);
        $this->lazyFilters['capitalize'] = fn() => fn(mixed $value) => $value === null ? '' : ucfirst(strtolower((string)$value));
        $this->lazyFilters['truncate'] = fn() => [$this, 'truncateFilter'];
        $this->lazyFilters['default'] = fn() => [$this, 'defaultFilter'];
        $this->lazyFilters['raw'] = fn() => [$this, 'rawFilter'];

        // Number/Format Filter - NULL-SAFE
        $this->lazyFilters['number_format'] = fn() => [$this, 'numberFormatFilter'];
        $this->lazyFilters['currency'] = fn() => [$this, 'currencyFilter'];

        // Date Filter
        $this->lazyFilters['date'] = fn() => [$this, 'dateFilter'];

        // Utility Filter
        $this->lazyFilters['length'] = fn() => [$this, 'lengthFilter'];
        $this->lazyFilters['count'] = fn() => [$this, 'lengthFilter'];
        $this->lazyFilters['plural'] = fn() => [$this, 'pluralFilter'];

        // Advanced Text Filter - NULL-SAFE
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

    // Filter Implementations - ALLE NULL-SAFE

    private function truncateFilter(mixed $value, int $length = 100, string $suffix = '...'): string
    {
        if ($value === null) {
            return '';
        }

        $stringValue = (string)$value;

        if (mb_strlen($stringValue) <= $length) {
            return $stringValue;
        }

        return mb_substr($stringValue, 0, $length) . $suffix;
    }

    private function defaultFilter(mixed $value, mixed $default = ''): mixed
    {
        return $value ?? $default;
    }

    private function rawFilter(mixed $value): mixed
    {
        return $value;
    }

    private function currencyFilter(mixed $value, string $currency = 'EUR', string $locale = 'de_DE'): string
    {
        if ($value === null) {
            return '0 ' . $currency;
        }

        $numericValue = is_numeric($value) ? (float)$value : 0.0;
        return $this->numberFormatFilter($numericValue, 2) . ' ' . $currency;
    }

    private function numberFormatFilter(mixed $value, int|string $decimals = 2, string $decimalPoint = ',', string $thousandsSeparator = '.'): string
    {
        if ($value === null) {
            return '0';
        }

        // Convert to float if possible, otherwise use 0
        $numericValue = is_numeric($value) ? (float)$value : 0.0;

        // Convert string to int for decimals parameter
        $decimals = is_string($decimals) ? (int)$decimals : $decimals;

        return number_format($numericValue, $decimals, $decimalPoint, $thousandsSeparator);
    }

    private function dateFilter(mixed $value, string $format = 'Y-m-d H:i:s'): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);
            return $timestamp !== false ? date($format, $timestamp) : '';
        }

        if (is_int($value)) {
            return date($format, $value);
        }

        return (string)$value;
    }

    private function lengthFilter(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

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

    private function slugFilter(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $stringValue = (string)$value;
        $stringValue = strtolower($stringValue);
        $stringValue = preg_replace('/[^a-z0-9]+/', '-', $stringValue);
        return trim($stringValue, '-');
    }

    private function nl2brFilter(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return nl2br((string)$value);
    }

    private function stripTagsFilter(mixed $value, string $allowedTags = ''): string
    {
        if ($value === null) {
            return '';
        }

        return strip_tags((string)$value, $allowedTags);
    }

    private function jsonFilter(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function firstFilter(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

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
        if ($value === null) {
            return null;
        }

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
    private function translateFilter(mixed $key, mixed ...$args): string
    {
        if ($this->translator === null || $key === null) {
            return (string)$key;
        }

        $parameters = [];
        if (!empty($args) && is_array($args[0])) {
            $parameters = $args[0];
        }

        return $this->translator->translate((string)$key, $parameters);
    }

    /**
     * Translation Plural Filter - Uses injected translator
     */
    private function translatePluralFilter(mixed $key, int $count, array $parameters = []): string
    {
        if ($this->translator === null || $key === null) {
            return (string)$key;
        }

        return $this->translator->translatePlural((string)$key, $count, $parameters);
    }
}