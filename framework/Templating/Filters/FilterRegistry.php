<?php


declare(strict_types=1);

namespace Framework\Templating\Filters;

use RuntimeException;

/**
 * FilterRegistry - Verwaltet Filter-Registrierung und Lazy Loading
 *
 * Verantwortlichkeiten:
 * - Filter-Registry (register, has, getFilterNames)
 * - Lazy Loading Mechanismus
 * - Filter-Storage Management
 */
class FilterRegistry
{
    /** @var array<string, callable> Bereits geladene Filter */
    private array $filters = [];

    /** @var array<string, callable> Lazy Filter Factories */
    private array $lazyFilters = [];

    /**
     * Registriert einen Filter direkt
     */
    public function register(string $name, callable $filter): void
    {
        $this->filters[$name] = $filter;
    }

    /**
     * Registriert einen Lazy Filter (wird erst bei Bedarf geladen)
     */
    public function registerLazy(string $name, callable $factory): void
    {
        $this->lazyFilters[$name] = $factory;
    }

    /**
     * Prüft ob ein Filter existiert (geladen oder lazy)
     */
    public function has(string $name): bool
    {
        return isset($this->filters[$name]) || isset($this->lazyFilters[$name]);
    }

    /**
     * Gibt einen Filter zurück, lädt ihn falls nötig
     */
    public function get(string $name): callable
    {
        // Bereits geladen?
        if (isset($this->filters[$name])) {
            return $this->filters[$name];
        }

        // Lazy loading
        if (isset($this->lazyFilters[$name])) {
            $factory = $this->lazyFilters[$name];
            $this->filters[$name] = $factory();
            return $this->filters[$name];
        }

        throw new RuntimeException("Filter '{$name}' not found");
    }

    /**
     * Entfernt einen Filter
     */
    public function remove(string $name): void
    {
        unset($this->filters[$name], $this->lazyFilters[$name]);
    }

    /**
     * Leert die Registry
     */
    public function clear(): void
    {
        $this->filters = [];
        $this->lazyFilters = [];
    }

    /**
     * Gibt die Anzahl der verfügbaren Filter zurück
     */
    public function count(): int
    {
        return count($this->getFilterNames());
    }

    /**
     * Gibt alle verfügbaren Filter-Namen zurück
     */
    public function getFilterNames(): array
    {
        return array_unique(array_merge(
            array_keys($this->filters),
            array_keys($this->lazyFilters)
        ));
    }
}