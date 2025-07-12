<?php
declare(strict_types=1);

namespace Framework\Templating;

use RuntimeException;

/**
 * Filter Manager - Verwaltet alle Template-Filter
 */
class FilterManager
{
    private array $filters = [];

    public function __construct()
    {
        $this->registerDefaultFilters();
    }

    /**
     * Registriert Standard-Filter
     */
    private function registerDefaultFilters(): void
    {
        // Text/String Filter
        $this->register('upper', fn(string $value) => strtoupper($value));
        $this->register('lower', fn(string $value) => strtolower($value));
        $this->register('capitalize', fn(string $value) => ucfirst(strtolower($value)));
        $this->register('truncate', [$this, 'truncateFilter']);
        $this->register('default', [$this, 'defaultFilter']);
        $this->register('raw', fn(string $value) => $value);

        // Number/Format Filter
        $this->register('number_format', [$this, 'numberFormatFilter']);
        $this->register('currency', [$this, 'currencyFilter']);

        // Date Filter
        $this->register('date', [$this, 'dateFilter']);

        // Utility Filter
        $this->register('length', [$this, 'lengthFilter']);
        $this->register('count', [$this, 'lengthFilter']);
        $this->register('plural', [$this, 'pluralFilter']);

        // Advanced Text Filter
        $this->register('slug', [$this, 'slugFilter']);
        $this->register('nl2br', [$this, 'nl2brFilter']);
        $this->register('strip_tags', [$this, 'stripTagsFilter']);

        // Advanced Utility Filter
        $this->register('json', [$this, 'jsonFilter']);
        $this->register('first', [$this, 'firstFilter']);
        $this->register('last', [$this, 'lastFilter']);
    }

    /**
     * Registriert einen neuen Filter
     */
    public function register(string $name, callable $callback): void
    {
        $this->filters[$name] = $callback;
    }

    /**
     * Wendet Filter auf Wert an
     */
    public function apply(string $filterName, mixed $value, array $parameters = []): mixed
    {
        if (!isset($this->filters[$filterName])) {
            throw new RuntimeException("Unknown filter: {$filterName}");
        }

        $filter = $this->filters[$filterName];

        // Parameter an Callback übergeben
        return call_user_func($filter, $value, ...$parameters);
    }

    /**
     * Prüft ob Filter existiert
     */
    public function has(string $filterName): bool
    {
        return isset($this->filters[$filterName]);
    }

    /**
     * Holt alle registrierten Filter
     */
    public function getRegisteredFilters(): array
    {
        return array_keys($this->filters);
    }

    /**
     * Truncate Filter - Kürzt Text mit Ellipsis
     */
    private function truncateFilter(string $value, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length) . $suffix;
    }

    /**
     * Default Filter - Fallback-Wert bei leerem/null Wert
     */
    private function defaultFilter(mixed $value, string $default = ''): mixed
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return $default;
        }

        return $value;
    }

    /**
     * Number Format Filter - Formatiert Zahlen
     */
    private function numberFormatFilter(
        mixed  $value,
        int    $decimals = 0,
        string $decimalSeparator = '.',
        string $thousandsSeparator = ','
    ): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }

        return number_format((float)$value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * Currency Filter - Formatiert Währungen
     */
    private function currencyFilter(
        mixed  $value,
        string $currency = '€',
        string $position = 'right',
        int    $decimals = 2
    ): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }

        $formatted = number_format((float)$value, $decimals, '.', ',');

        return match ($position) {
            'left' => $currency . $formatted,
            'right' => $formatted . ' ' . $currency,
            default => $formatted . ' ' . $currency,
        };
    }

    /**
     * Date Filter - Formatiert Datumsangaben
     */
    private function dateFilter(mixed $value, string $format = 'Y-m-d'): string
    {
        if (empty($value)) {
            return '';
        }

        // String zu Timestamp konvertieren
        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return (string)$value; // Fallback: Original-String
            }
        } elseif (is_int($value)) {
            $timestamp = $value;
        } else {
            return (string)$value;
        }

        return date($format, $timestamp);
    }

    /**
     * Plural Filter - Einfache Pluralisierung
     */
    private function pluralFilter(mixed $value, string $singular, string $plural): string
    {
        $count = is_numeric($value) ? (int)$value : $this->lengthFilter($value);

        return $count === 1 ? $singular : $plural;
    }

    /**
     * Length/Count Filter - Gibt Länge von String/Array zurück
     */
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

    /**
     * Slug Filter - URL-freundliche Strings für SEO
     */
    private function slugFilter(string $value): string
    {
        // Convert to lowercase
        $slug = strtolower($value);

        // Replace umlauts and special characters
        $replacements = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ñ' => 'n', 'ç' => 'c',
        ];

        $slug = strtr($slug, $replacements);

        // Remove non-alphanumeric characters (except hyphens)
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);

        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Trim hyphens from start and end
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Newline to BR Filter - Konvertiert \n zu <br> Tags
     */
    private function nl2brFilter(string $value): string
    {
        return nl2br($value, true); // XHTML compliant <br />
    }

    /**
     * Strip Tags Filter - Entfernt HTML-Tags
     */
    private function stripTagsFilter(string $value, string $allowedTags = ''): string
    {
        return strip_tags($value, $allowedTags);
    }

    /**
     * JSON Filter - Konvertiert Array zu JSON-String
     */
    private function jsonFilter(mixed $value, int $flags = JSON_UNESCAPED_UNICODE): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | $flags);
        } catch (\JsonException $e) {
            error_log("JSON filter error: " . $e->getMessage());
            return '{}'; // Fallback
        }
    }

    /**
     * First Filter - Erstes Element eines Arrays
     */
    private function firstFilter(mixed $value): mixed
    {
        if (is_array($value) && !empty($value)) {
            return reset($value); // Erstes Element
        }

        if (is_string($value) && strlen($value) > 0) {
            return $value[0]; // Erster Buchstabe
        }

        return null;
    }

    /**
     * Last Filter - Letztes Element eines Arrays
     */
    private function lastFilter(mixed $value): mixed
    {
        if (is_array($value) && !empty($value)) {
            return end($value); // Letztes Element
        }

        if (is_string($value) && strlen($value) > 0) {
            return $value[strlen($value) - 1]; // Letzter Buchstabe
        }

        return null;
    }
}