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
 * FilterManager - KRITISCHER FIX: applyPipeline Methode hinzugefügt
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
        $this->registerJsonFilters();

        if ($this->translator !== null) {
            $this->registerTranslationFilters();
        }
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

        // Versuche erweiterte Filter zu registrieren
        $this->registerIfExists('truncate', [TextFilters::class, 'truncate']);
        $this->registerIfExists('slug', [TextFilters::class, 'slug']);
        $this->registerIfExists('nl2br', [TextFilters::class, 'nl2br']);
        $this->registerIfExists('strip_tags', [TextFilters::class, 'stripTags']);
        $this->registerIfExists('replace', [TextFilters::class, 'replace']);
        $this->registerIfExists('repeat', [TextFilters::class, 'repeat']);
        $this->registerIfExists('reverse', [TextFilters::class, 'reverse']);
    }

    /**
     * Number-Filter registrieren
     */
    private function registerNumberFilters(): void
    {
        // Basis-Number-Filter
        $this->registerFallback('number_format', function($value, $decimals = 0) {
            return number_format((float)$value, (int)$decimals);
        });
        $this->registerFallback('currency', function($value, $currency = '€') {
            return number_format((float)$value, 2, ',', '.') . ' ' . $currency;
        });
        $this->registerFallback('abs', fn($value) => abs((float)$value));
        $this->registerFallback('round', fn($value, $precision = 0) => round((float)$value, (int)$precision));
        $this->registerFallback('ceil', fn($value) => ceil((float)$value));
        $this->registerFallback('floor', fn($value) => floor((float)$value));

        // Erweiterte Number-Filter
        $this->registerIfExists('currency_format', [NumberFilters::class, 'currency']);
        $this->registerIfExists('percentage', [NumberFilters::class, 'percentage']);
    }

    /**
     * Date-Filter registrieren
     */
    private function registerDateFilters(): void
    {
        // Basis-Date-Filter
        $this->registerFallback('date', function($value, $format = 'Y-m-d H:i:s') {
            if (is_int($value)) return date($format, $value);
            if (is_string($value)) {
                $timestamp = strtotime($value);
                return $timestamp !== false ? date($format, $timestamp) : $value;
            }
            return $value;
        });

        $this->registerFallback('time', fn($value) => date('H:i:s', is_int($value) ? $value : strtotime((string)$value)));
        $this->registerFallback('timestamp', fn($value) => is_int($value) ? $value : strtotime((string)$value));

        $this->registerFallback('relative_time', function($value) {
            $timestamp = is_int($value) ? $value : strtotime((string)$value);
            if ($timestamp === false) return 'unbekannt';

            $diff = time() - $timestamp;
            if ($diff < 60) return 'vor ' . $diff . ' Sekunden';
            if ($diff < 3600) return 'vor ' . floor($diff / 60) . ' Minuten';
            if ($diff < 86400) return 'vor ' . floor($diff / 3600) . ' Stunden';
            return 'vor ' . floor($diff / 86400) . ' Tagen';
        });

        // Erweiterte Date-Filter
        $this->registerIfExists('time_ago', [DateFilters::class, 'timeAgo']);
        $this->registerIfExists('format_date', [DateFilters::class, 'formatDate']);
    }

    /**
     * Utility-Filter registrieren
     */
    private function registerUtilityFilters(): void
    {
        // Array-Filter
        $this->registerFallback('first', fn($array) => is_array($array) && !empty($array) ? reset($array) : null);
        $this->registerFallback('last', fn($array) => is_array($array) && !empty($array) ? end($array) : null);
        $this->registerFallback('count', fn($value) => is_array($value) ? count($value) : (is_string($value) ? strlen($value) : 0));
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
    }

    /**
     * JSON-Filter registrieren
     */
    private function registerJsonFilters(): void
    {
        $this->registerFallback('json_pretty', fn($value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->registerIfExists('json_encode', [JsonFilters::class, 'jsonEncode']);
        $this->registerIfExists('json_decode', [JsonFilters::class, 'jsonDecode']);
    }

    /**
     * Translation-Filter registrieren
     */
    private function registerTranslationFilters(): void
    {
        if ($this->translator === null) {
            // Fallback wenn kein Translator verfügbar
            $this->registerFallback('t', fn($value) => (string)$value);
            $this->registerFallback('translate', fn($value) => (string)$value);
            return;
        }

        $translationFilters = new TranslationFilters($this->translator);

        $this->registerIfExists('t', [$translationFilters, 't']);
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
}