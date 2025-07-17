<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Templating\Filters\FilterRegistry;
use Framework\Templating\Filters\FilterExecutor;
use Framework\Templating\Filters\TextFilters;
use Framework\Templating\Filters\NumberFilters;
use Framework\Templating\Filters\DateFilters;
use Framework\Templating\Filters\UtilityFilters;
use Framework\Templating\Filters\TranslationFilters;
use Framework\Localization\Translator;

/**
 * FilterManager - Schlanke Facade für Filter-System
 *
 * REFACTORED: Neue Architektur mit klarer Trennung der Verantwortlichkeiten
 * - FilterRegistry: Filter-Verwaltung + Lazy Loading
 * - FilterExecutor: Ausführungslogik + Pipeline-Support
 * - Filter-Klassen: Konkrete Implementierungen (statische Methoden)
 * - FilterManager: Schlanke Facade (nur Koordination)
 *
 * SRP-konforme Lösung: Jede Klasse hat eine klare Verantwortlichkeit
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

        if ($this->translator !== null) {
            $this->registerTranslationFilters();
        }
    }

    /**
     * Registriert Text-Filter
     */
    private function registerTextFilters(): void
    {
        $this->registry->registerLazy('upper', fn() => [TextFilters::class, 'upper']);
        $this->registry->registerLazy('lower', fn() => [TextFilters::class, 'lower']);
        $this->registry->registerLazy('capitalize', fn() => [TextFilters::class, 'capitalize']);
        $this->registry->registerLazy('truncate', fn() => [TextFilters::class, 'truncate']);
        $this->registry->registerLazy('slug', fn() => [TextFilters::class, 'slug']);
        $this->registry->registerLazy('nl2br', fn() => [TextFilters::class, 'nl2br']);
        $this->registry->registerLazy('strip_tags', fn() => [TextFilters::class, 'stripTags']);
        $this->registry->registerLazy('raw', fn() => [TextFilters::class, 'raw']);
        $this->registry->registerLazy('default', fn() => [TextFilters::class, 'default']);
        $this->registry->registerLazy('trim', fn() => [TextFilters::class, 'trim']);
        $this->registry->registerLazy('replace', fn() => [TextFilters::class, 'replace']);
        $this->registry->registerLazy('repeat', fn() => [TextFilters::class, 'repeat']);
        $this->registry->registerLazy('reverse', fn() => [TextFilters::class, 'reverse']);
    }

    /**
     * Registriert Number-Filter
     */
    private function registerNumberFilters(): void
    {
        $this->registry->registerLazy('number_format', fn() => [NumberFilters::class, 'numberFormat']);
        $this->registry->registerLazy('currency', fn() => [NumberFilters::class, 'currency']);
        $this->registry->registerLazy('percent', fn() => [NumberFilters::class, 'percent']);
        $this->registry->registerLazy('round', fn() => [NumberFilters::class, 'round']);
        $this->registry->registerLazy('ceil', fn() => [NumberFilters::class, 'ceil']);
        $this->registry->registerLazy('floor', fn() => [NumberFilters::class, 'floor']);
        $this->registry->registerLazy('abs', fn() => [NumberFilters::class, 'abs']);
        $this->registry->registerLazy('file_size', fn() => [NumberFilters::class, 'fileSize']);
        $this->registry->registerLazy('ordinal', fn() => [NumberFilters::class, 'ordinal']);
    }

    /**
     * Registriert Date-Filter
     */
    private function registerDateFilters(): void
    {
        $this->registry->registerLazy('date', fn() => [DateFilters::class, 'date']);
        $this->registry->registerLazy('date_german', fn() => [DateFilters::class, 'dateGerman']);
        $this->registry->registerLazy('datetime', fn() => [DateFilters::class, 'datetime']);
        $this->registry->registerLazy('datetime_german', fn() => [DateFilters::class, 'datetimeGerman']);
        $this->registry->registerLazy('time', fn() => [DateFilters::class, 'time']);
        $this->registry->registerLazy('time_ago', fn() => [DateFilters::class, 'timeAgo']);
        $this->registry->registerLazy('day_of_week', fn() => [DateFilters::class, 'dayOfWeek']);
        $this->registry->registerLazy('month_name', fn() => [DateFilters::class, 'monthName']);
        $this->registry->registerLazy('timestamp', fn() => [DateFilters::class, 'timestamp']);
    }

    /**
     * Registriert Utility-Filter
     */
    private function registerUtilityFilters(): void
    {
        $this->registry->registerLazy('length', fn() => [UtilityFilters::class, 'length']);
        $this->registry->registerLazy('count', fn() => [UtilityFilters::class, 'count']);
        $this->registry->registerLazy('first', fn() => [UtilityFilters::class, 'first']);
        $this->registry->registerLazy('last', fn() => [UtilityFilters::class, 'last']);
        $this->registry->registerLazy('json', fn() => [UtilityFilters::class, 'json']);
        $this->registry->registerLazy('is_empty', fn() => [UtilityFilters::class, 'isEmpty']);
        $this->registry->registerLazy('is_not_empty', fn() => [UtilityFilters::class, 'isNotEmpty']);
        $this->registry->registerLazy('type', fn() => [UtilityFilters::class, 'type']);
        $this->registry->registerLazy('plural', fn() => [UtilityFilters::class, 'plural']);
        $this->registry->registerLazy('sort', fn() => [UtilityFilters::class, 'sort']);
        $this->registry->registerLazy('random', fn() => [UtilityFilters::class, 'random']);
        $this->registry->registerLazy('join', fn() => [UtilityFilters::class, 'join']);
        $this->registry->registerLazy('split', fn() => [UtilityFilters::class, 'split']);
        $this->registry->registerLazy('unique', fn() => [UtilityFilters::class, 'unique']);
    }

    /**
     * Registriert Translation-Filter
     */
    private function registerTranslationFilters(): void
    {
        $translationFilters = new TranslationFilters($this->translator);

        $this->registry->register('t', [$translationFilters, 'translate']);
        $this->registry->register('translate', [$translationFilters, 'translate']);
        $this->registry->register('tp', [$translationFilters, 'translatePlural']);
        $this->registry->register('translate_plural', [$translationFilters, 'translatePlural']);
        $this->registry->register('has_translation', [$translationFilters, 'hasTranslation']);
        $this->registry->register('locale', [$translationFilters, 'locale']);
        $this->registry->register('translate_in', [$translationFilters, 'translateIn']);
    }

    /**
     * Führt Filter aus (Hauptschnittstelle)
     */
    public function apply(string $filterName, mixed $value, array $parameters = []): mixed
    {
        return $this->executor->execute($filterName, $value, $parameters);
    }

    /**
     * Prüft ob Filter existiert
     */
    public function has(string $filterName): bool
    {
        return $this->executor->hasFilter($filterName);
    }

    /**
     * Registriert Custom Filter
     */
    public function register(string $name, callable $filter): void
    {
        $this->registry->register($name, $filter);
    }

    /**
     * Gibt alle verfügbaren Filter zurück
     */
    public function getFilterNames(): array
    {
        return $this->executor->getAvailableFilters();
    }

    /**
     * Führt mehrere Filter nacheinander aus
     */
    public function applyPipeline(mixed $value, array $filterPipeline): mixed
    {
        return $this->executor->executePipeline($value, $filterPipeline);
    }

    /**
     * Entfernt einen Filter
     */
    public function remove(string $name): void
    {
        $this->registry->remove($name);
    }

    /**
     * Gibt Registry zurück (für erweiterte Nutzung)
     */
    public function getRegistry(): FilterRegistry
    {
        return $this->registry;
    }

    /**
     * Gibt Executor zurück (für erweiterte Nutzung)
     */
    public function getExecutor(): FilterExecutor
    {
        return $this->executor;
    }
}