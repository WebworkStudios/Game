<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Localization\Translator;
use Framework\Templating\Filters\DateFilters;
use Framework\Templating\Filters\FilterExecutor;
use Framework\Templating\Filters\FilterRegistry;
use Framework\Templating\Filters\JsonFilters;
use Framework\Templating\Filters\NumberFilters;
use Framework\Templating\Filters\TextFilters;
use Framework\Templating\Filters\TranslationFilters;
use Framework\Templating\Filters\UtilityFilters;

/**
 * FilterManager - GEFIXT: JSON-Filter und alle fehlenden Standard-Filter hinzugefügt
 */
class FilterManager
{
    private FilterRegistry $registry;
    private FilterExecutor $executor;

    public function __construct(
        private readonly ?Translator $translator = null
    ) {
        $this->registry = new FilterRegistry();
        $this->executor = new FilterExecutor($this->registry);

        $this->registerDefaultFilters();
    }

    /**
     * KRITISCHER FIX: applyPipeline Methode die vom TemplateRenderer erwartet wird
     */
    public function applyPipeline(mixed $value, array $filters): mixed
    {
        $result = $value;

        foreach ($filters as $filter) {
            if (is_string($filter)) {
                // Einfacher Filter ohne Argumente: "upper"
                $result = $this->execute($filter, $result);
            } elseif (is_array($filter)) {
                // Filter mit Argumenten: ["number_format", [2]]
                $filterName = $filter['name'] ?? $filter[0] ?? '';
                $arguments = $filter['arguments'] ?? $filter[1] ?? [];

                if ($filterName) {
                    $result = $this->execute($filterName, $result, $arguments);
                }
            }
        }

        return $result;
    }

    /**
     * Einzelnen Filter ausführen
     */
    public function execute(string $filterName, mixed $value, array $arguments = []): mixed
    {
        return $this->executor->execute($filterName, $value, $arguments);
    }

    /**
     * Prüft ob Filter existiert
     */
    public function has(string $name): bool
    {
        return $this->registry->has($name);
    }

    /**
     * Filter registrieren
     */
    public function register(string $name, callable $filter): void
    {
        $this->registry->register($name, $filter);
    }

    /**
     * Alle verfügbaren Filter abrufen
     */
    public function getAvailableFilters(): array
    {
        return $this->registry->getAvailableFilterNames();
    }

    /**
     * Registry-Zugriff
     */
    public function getRegistry(): FilterRegistry
    {
        return $this->registry;
    }

    /**
     * Registriert alle Standard-Filter
     */
    private function registerDefaultFilters(): void
    {
        $this->registerTextFilters();
        $this->registerNumberFilters();
        $this->registerDateFilters();
        $this->registerUtilityFilters();
        $this->registerJsonFilters(); // KRITISCH: Diese Methode wird aufgerufen
        $this->registerTranslationFilters();
    }

    /**
     * Text-Filter registrieren
     */
    private function registerTextFilters(): void
    {
        // Basis-Filter die immer funktionieren
        $this->registerFallback('upper', fn($value) => strtoupper((string)$value));
        $this->registerFallback('lower', fn($value) => strtolower((string)$value));
        $this->registerFallback('capitalize', fn($value) => ucfirst(strtolower((string)$value)));
        $this->registerFallback('title', fn($value) => ucwords(strtolower((string)$value)));
        $this->registerFallback('trim', fn($value) => trim((string)$value));
        $this->registerFallback('length', fn($value) => is_array($value) ? count($value) : strlen((string)$value));
        $this->registerFallback('escape', fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->registerFallback('e', fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->registerFallback('raw', fn($value) => $value);
        $this->registerFallback('default', fn($value, $default = '') => empty($value) ? $default : $value);

        // Erweiterte Text-Filter falls Klasse verfügbar
        $this->registerIfExists('truncate', [TextFilters::class, 'truncate']);
        $this->registerIfExists('slug', [TextFilters::class, 'slug']);
        $this->registerIfExists('nl2br', [TextFilters::class, 'nl2br']);
        $this->registerIfExists('strip_tags', [TextFilters::class, 'stripTags']);
    }

    /**
     * Number-Filter registrieren
     */
    private function registerNumberFilters(): void
    {
        // Basis Number-Filter
        $this->registerFallback('number_format', function($value, $decimals = 0, $decimalPoint = ',', $thousandsSep = '.') {
            return number_format((float)$value, (int)$decimals, $decimalPoint, $thousandsSep);
        });

        $this->registerFallback('round', fn($value, $precision = 0) => round((float)$value, (int)$precision));
        $this->registerFallback('floor', fn($value) => floor((float)$value));
        $this->registerFallback('ceil', fn($value) => ceil((float)$value));
        $this->registerFallback('abs', fn($value) => abs((float)$value));

        // Erweiterte Number-Filter falls Klasse verfügbar
        $this->registerIfExists('currency', [NumberFilters::class, 'currency']);
        $this->registerIfExists('percent', [NumberFilters::class, 'percent']);
        $this->registerIfExists('filesize', [NumberFilters::class, 'filesize']);
    }

    /**
     * Date-Filter registrieren
     */
    private function registerDateFilters(): void
    {
        // Basis Date-Filter
        $this->registerFallback('date', function($value, $format = 'Y-m-d H:i:s') {
            if (empty($value)) return '';

            if (is_numeric($value)) {
                return date($format, (int)$value);
            }

            $timestamp = strtotime((string)$value);
            return $timestamp ? date($format, $timestamp) : (string)$value;
        });

        // Erweiterte Date-Filter falls Klasse verfügbar
        $this->registerIfExists('time_ago', [DateFilters::class, 'timeAgo']);
        $this->registerIfExists('date_format', [DateFilters::class, 'dateFormat']);
    }

    /**
     * Utility-Filter registrieren
     */
    private function registerUtilityFilters(): void
    {
        // Basis Utility-Filter
        $this->registerFallback('length', fn($value) => is_array($value) ? count($value) : (is_string($value) ? strlen($value) : 0));
        $this->registerFallback('join', fn($array, $separator = ', ') => is_array($array) ? implode($separator, $array) : (string)$array);

        $this->registerFallback('sort', function($array) {
            if (!is_array($array)) return $array;
            sort($array);
            return $array;
        });

        $this->registerFallback('unique', fn($array) => is_array($array) ? array_unique($array) : $array);
        $this->registerFallback('slice', fn($array, $start, $length = null) => is_array($array) ? array_slice($array, (int)$start, $length) : $array);

        // Erweiterte Utility-Filter
        $this->registerIfExists('map', [UtilityFilters::class, 'map']);
        $this->registerIfExists('filter_empty', [UtilityFilters::class, 'filterEmpty']);
        $this->registerIfExists('first', [UtilityFilters::class, 'first']);
        $this->registerIfExists('last', [UtilityFilters::class, 'last']);
    }

    /**
     * GEFIXT: JSON-Filter registrieren - FEHLENDER STANDARD-JSON-FILTER HINZUGEFÜGT
     */
    private function registerJsonFilters(): void
    {
        // KRITISCH: Standard 'json' Filter hinzufügen
        $this->registerFallback('json', function($value) {
            try {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (\Throwable $e) {
                error_log("JSON encoding error: " . $e->getMessage());
                return '{}';
            }
        });

        // Pretty JSON Filter
        $this->registerFallback('json_pretty', function($value) {
            try {
                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (\Throwable $e) {
                error_log("JSON pretty encoding error: " . $e->getMessage());
                return '{}';
            }
        });

        // JSON Decode Filter
        $this->registerFallback('json_decode', function($value) {
            try {
                if (is_string($value)) {
                    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                }
                return $value;
            } catch (\Throwable $e) {
                error_log("JSON decoding error: " . $e->getMessage());
                return null;
            }
        });

        // Erweiterte JSON-Filter falls Klasse verfügbar
        $this->registerIfExists('json_encode', [JsonFilters::class, 'jsonEncode']);
    }

    /**
     * Translation-Filter registrieren
     */
    private function registerTranslationFilters(): void
    {
        if ($this->translator === null) {
            // Fallback wenn kein Translator verfügbar
            $this->registerFallback('t', fn($value) => (string)$value);
            $this->registerFallback('trans', fn($value) => (string)$value);
            $this->registerFallback('translate', fn($value) => (string)$value);
            return;
        }

        $translationFilters = new TranslationFilters($this->translator);

        $this->registerIfExists('t', [$translationFilters, 't']);
        $this->registerIfExists('trans', [$translationFilters, 't']); // Alias
        $this->registerIfExists('translate', [$translationFilters, 'translate']);
        $this->registerIfExists('tp', [$translationFilters, 'tp']);
        $this->registerIfExists('translate_plural', [$translationFilters, 'translatePlural']);
    }

    /**
     * Registriert Filter nur wenn Methode existiert
     */
    private function registerIfExists(string $name, array $callable): void
    {
        [$class, $method] = $callable;

        if (class_exists($class) && method_exists($class, $method)) {
            try {
                $this->registry->register($name, $callable);
            } catch (\Throwable $e) {
                error_log("Failed to register filter '{$name}': " . $e->getMessage());
                // Fallback: Identity filter
                $this->registerFallback($name, fn($value, ...$args) => $value);
            }
        }
    }

    /**
     * Registriert Fallback-Filter
     */
    private function registerFallback(string $name, callable $filter): void
    {
        if (!$this->registry->has($name)) {
            $this->registry->register($name, $filter);
        }
    }

    /**
     * ZUSÄTZLICH: Debug-Methode um alle registrierten Filter zu sehen
     */
    public function debugFilters(): array
    {
        $filters = $this->getAvailableFilters();
        sort($filters);

        return [
            'count' => count($filters),
            'filters' => $filters
        ];
    }
}