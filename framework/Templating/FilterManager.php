<?php

declare(strict_types=1);

namespace Framework\Templating;

use RuntimeException;

/**
 * Filter Manager - Verwaltet alle Template-Filter mit Lazy Loading und XSS-Schutz
 */
class FilterManager
{
    private array $filters = [];
    private array $lazyFilters = [];

    public function __construct()
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

        // Translation filters (heavy - definitely lazy load)
        $this->lazyFilters['t'] = fn() => [$this, 'translateFilter'];
        $this->lazyFilters['t_plural'] = fn() => [$this, 'translatePluralFilter'];

        // *** XSS-SCHUTZ FILTER ***
        $this->lazyFilters['escape'] = fn() => [$this, 'escapeFilter'];
        $this->lazyFilters['e'] = fn() => [$this, 'escapeFilter']; // Alias
    }

    /**
     * Wendet Filter auf Wert an (mit Lazy Loading und verbesserter Fehlerbehandlung)
     */
    public function apply(string $filterName, mixed $value, array $parameters = []): mixed
    {
        // Load filter on-demand
        if (!isset($this->filters[$filterName])) {
            $this->loadFilter($filterName);
        }

        if (!isset($this->filters[$filterName])) {
            // Check if it looks like function syntax (contains parentheses)
            if (str_contains($filterName, '(') && str_contains($filterName, ')')) {
                // Extract actual filter name from function-like syntax
                $actualFilterName = preg_replace('/\([^)]*\)/', '', $filterName);

                throw new RuntimeException(
                    "Unknown filter: '{$filterName}'. " .
                    "Template syntax uses colon separators, not parentheses. " .
                    "Try: |{$actualFilterName}:param instead of |{$filterName}"
                );
            }

            // Check for common typos or similar filter names
            $suggestion = $this->suggestSimilarFilter($filterName);
            $errorMessage = "Unknown filter: '{$filterName}'";

            if ($suggestion) {
                $errorMessage .= ". Did you mean '{$suggestion}'?";
            }

            throw new RuntimeException($errorMessage);
        }

        $filter = $this->filters[$filterName];
        return call_user_func($filter, $value, ...$parameters);
    }

    /**
     * Schlägt ähnliche Filter-Namen vor bei Tippfehlern
     */
    private function suggestSimilarFilter(string $filterName): ?string
    {
        $availableFilters = array_keys($this->lazyFilters);
        $lowerFilterName = strtolower($filterName);

        // Exact match case-insensitive
        foreach ($availableFilters as $available) {
            if (strtolower($available) === $lowerFilterName) {
                return $available;
            }
        }

        // Levenshtein distance for typos
        $closest = null;
        $shortestDistance = PHP_INT_MAX;

        foreach ($availableFilters as $available) {
            $distance = levenshtein($lowerFilterName, strtolower($available));

            // Only suggest if distance is small (max 2 character difference)
            if ($distance < $shortestDistance && $distance <= 2) {
                $shortestDistance = $distance;
                $closest = $available;
            }
        }

        return $closest;
    }

    /**
     * Lädt einen Filter bei Bedarf
     */
    private function loadFilter(string $filterName): void
    {
        if (!isset($this->lazyFilters[$filterName])) {
            return;
        }

        // Execute lazy loader
        $loader = $this->lazyFilters[$filterName];
        $this->filters[$filterName] = $loader();

        // Performance: Remove lazy definition after loading
        unset($this->lazyFilters[$filterName]);
    }

    /**
     * Prüft ob Filter existiert (inklusive Lazy)
     */
    public function has(string $filterName): bool
    {
        return isset($this->filters[$filterName]) || isset($this->lazyFilters[$filterName]);
    }

    /**
     * Holt alle verfügbaren Filter (ohne sie zu laden)
     */
    public function getAvailableFilters(): array
    {
        return array_merge(
            array_keys($this->filters),
            array_keys($this->lazyFilters)
        );
    }

    /**
     * Holt geladene Filter (für Performance-Monitoring)
     */
    public function getLoadedFilters(): array
    {
        return array_keys($this->filters);
    }

    /**
     * Registriert einen neuen Filter (sofort geladen)
     */
    public function register(string $name, callable $callback): void
    {
        $this->filters[$name] = $callback;
    }

    /**
     * Registriert einen Lazy Filter
     */
    public function registerLazy(string $name, callable $loader): void
    {
        $this->lazyFilters[$name] = $loader;
    }

    // ===================================================================
    // FILTER IMPLEMENTATIONS
    // ===================================================================

    /**
     * Raw Filter - Gibt Inhalt ohne HTML-Escaping aus
     */
    private function rawFilter(mixed $value): mixed
    {
        // Raw Filter macht nichts - verhindert nur das automatische Escaping
        return $value;
    }

    /**
     * Escape Filter - Explizites HTML-Escaping mit verschiedenen Strategien
     */
    private function escapeFilter(mixed $value, string $strategy = 'html'): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return match ($strategy) {
            'html' => htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8',
                true
            ),
            'attr' => htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8',
                true
            ),
            'js', 'json' => $this->jsonFilter($value), // Nutze existierenden jsonFilter
            'css' => $this->escapeCss($value),
            'url' => rawurlencode($value),
            default => htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8',
                true
            ),
        };
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
     * CSS-String escaping
     */
    private function escapeCss(string $value): string
    {
        // Entferne gefährliche CSS-Zeichen
        return preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $value);
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
     * Date Filter - Formatiert Datum
     */
    private function dateFilter(mixed $value, string $format = 'Y-m-d H:i:s'): string
    {
        if (empty($value)) {
            return '';
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return $value; // Return original if parsing fails
            }
        } elseif (is_int($value)) {
            $timestamp = $value;
        } else {
            return (string)$value;
        }

        return date($format, $timestamp);
    }

    /**
     * Plural Filter - Singular/Plural basierend auf Anzahl
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

    /**
     * Translation Filter - Lazy loaded mit ServiceRegistry
     */
    private function translateFilter(string $key, mixed ...$args): string
    {
        static $translator = null;
        if ($translator === null) {
            try {
                $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
            } catch (\Throwable) {
                return $key;
            }
        }

        $parameters = [];
        if (!empty($args) && is_array($args[0])) {
            $parameters = $args[0];
        }

        return $translator->translate($key, $parameters);
    }

    /**
     * Translation Plural Filter - Lazy loaded
     */
    private function translatePluralFilter(string $key, int $count, array $parameters = []): string
    {
        static $translator = null;
        if ($translator === null) {
            try {
                $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
            } catch (\Throwable) {
                return $key;
            }
        }

        return $translator->translatePlural($key, $count, $parameters);
    }
}