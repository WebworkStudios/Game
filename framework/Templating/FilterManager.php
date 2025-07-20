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
 * FilterManager - MINIMALER FIX für bestehende Filter-Klassen
 *
 * Verwendet bestehende Filter-Klassen ohne sie zu ändern!
 * Löst nur das Array-Callable Problem.
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
     * Text-Filter mit BESTEHENDEN Klassen registrieren
     */
    private function registerTextFilters(): void
    {
        // Nur die Filter registrieren, die WIRKLICH existieren
        $this->registerIfExists('upper', [TextFilters::class, 'upper']);
        $this->registerIfExists('lower', [TextFilters::class, 'lower']);
        $this->registerIfExists('capitalize', [TextFilters::class, 'capitalize']);
        $this->registerIfExists('truncate', [TextFilters::class, 'truncate']);
        $this->registerIfExists('slug', [TextFilters::class, 'slug']);
        $this->registerIfExists('nl2br', [TextFilters::class, 'nl2br']);
        $this->registerIfExists('strip_tags', [TextFilters::class, 'stripTags']);
        $this->registerIfExists('raw', [TextFilters::class, 'raw']);
        $this->registerIfExists('trim', [TextFilters::class, 'trim']);
        $this->registerIfExists('replace', [TextFilters::class, 'replace']);
        $this->registerIfExists('repeat', [TextFilters::class, 'repeat']);
        $this->registerIfExists('reverse', [TextFilters::class, 'reverse']);

        // Fallback für fehlende Methoden
        $this->registerFallback('title', fn($value) => ucwords(strtolower((string)$value)));
        $this->registerFallback('length', fn($value) => is_array($value) ? count($value) : mb_strlen((string)$value));
        $this->registerFallback('escape', fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
        $this->registerFallback('e', fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
        $this->registerFallback('default', fn($value, $default = '') => $value ?: $default);
    }

    /**
     * Number-Filter mit BESTEHENDEN Klassen registrieren
     */
    private function registerNumberFilters(): void
    {
        $this->registerIfExists('number_format', [NumberFilters::class, 'numberFormat']);
        $this->registerIfExists('currency', [NumberFilters::class, 'currency']);
        $this->registerIfExists('percentage', [NumberFilters::class, 'percentage']);
        $this->registerIfExists('abs', [NumberFilters::class, 'abs']);
        $this->registerIfExists('round', [NumberFilters::class, 'round']);

        // Fallback für fehlende Methoden
        $this->registerFallback('ceil', fn($value) => (int) ceil((float) $value));
        $this->registerFallback('floor', fn($value) => (int) floor((float) $value));
    }

    /**
     * Date-Filter mit BESTEHENDEN Klassen registrieren
     */
    private function registerDateFilters(): void
    {
        $this->registerIfExists('date_format', [DateFilters::class, 'dateFormat']);
        $this->registerIfExists('date', [DateFilters::class, 'date']);
        $this->registerIfExists('time', [DateFilters::class, 'time']);
        $this->registerIfExists('relative_time', [DateFilters::class, 'relativeTime']);
        $this->registerIfExists('timestamp', [DateFilters::class, 'timestamp']);

        // Alias für Kompatibilität - timeAgo ist die ECHTE Methode
        $this->registerFallback('relative_time', fn($value) =>
        method_exists(DateFilters::class, 'timeAgo')
            ? DateFilters::timeAgo($value)
            : 'vor unbekannter Zeit'
        );
    }

    /**
     * Utility-Filter mit BESTEHENDEN Klassen registrieren
     */
    private function registerUtilityFilters(): void
    {
        $this->registerIfExists('first', [UtilityFilters::class, 'first']);
        $this->registerIfExists('last', [UtilityFilters::class, 'last']);
        $this->registerIfExists('length', [UtilityFilters::class, 'length']);
        $this->registerIfExists('count', [UtilityFilters::class, 'count']);

        // Fallback für weitere Utility-Filter
        $this->registerFallback('join', fn($array, $separator = ', ') =>
        is_array($array) ? implode($separator, $array) : (string)$array);
        $this->registerFallback('sort', fn($array) =>
        is_array($array) ? (sort($array) ? $array : $array) : $array);
        $this->registerFallback('unique', fn($array) =>
        is_array($array) ? array_unique($array) : $array);
        $this->registerFallback('slice', fn($array, $start, $length = null) =>
        is_array($array) ? array_slice($array, $start, $length) : $array);
    }

    /**
     * JSON-Filter mit BESTEHENDEN Klassen registrieren
     */
    private function registerJsonFilters(): void
    {
        $this->registerIfExists('json_encode', [JsonFilters::class, 'jsonEncode']);
        $this->registerIfExists('json_decode', [JsonFilters::class, 'jsonDecode']);
        $this->registerIfExists('json_pretty', [JsonFilters::class, 'jsonPretty']);
    }

    /**
     * Translation-Filter mit BESTEHENDER Klasse registrieren
     */
    private function registerTranslationFilters(): void
    {
        $translationFilters = new TranslationFilters($this->translator);

        $this->registerIfExists('t', [$translationFilters, 't']);
        $this->registerIfExists('translate', [$translationFilters, 'translate']);
        $this->registerIfExists('tp', [$translationFilters, 'tp']);
        $this->registerIfExists('translate_plural', [$translationFilters, 'translatePlural']);
        $this->registerIfExists('has_translation', [$translationFilters, 'hasTranslation']);
        $this->registerIfExists('locale', [$translationFilters, 'locale']);
        $this->registerIfExists('translate_in', [$translationFilters, 'translateIn']);
    }

    /**
     * Registriert Filter nur wenn Methode existiert
     */
    private function registerIfExists(string $name, array $callable): void
    {
        [$class, $method] = $callable;

        if (method_exists($class, $method)) {
            try {
                $this->registry->register($name, $callable);
            } catch (\Throwable) {
                // Fallback registrieren wenn Array-Callable fehlschlägt
                $this->registerFallback($name, fn($value, ...$args) => $class::$method($value, ...$args));
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

    // Public API - unverändert
    public function register(string $name, callable $filter): void
    {
        $this->registry->register($name, $filter);
    }

    public function has(string $name): bool
    {
        return $this->registry->has($name);
    }

    public function execute(string $filterName, mixed $value, array $arguments = []): mixed
    {
        return $this->executor->execute($filterName, $value, $arguments);
    }

    public function getRegistry(): FilterRegistry
    {
        return $this->registry;
    }

    public function getAvailableFilters(): array
    {
        return $this->registry->getAvailableFilterNames();
    }
}