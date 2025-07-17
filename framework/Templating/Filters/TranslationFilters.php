<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

use Framework\Localization\Translator;

/**
 * TranslationFilters - Übersetzungs-Filter
 *
 * Benötigt Translator-Instanz als Dependency
 */
class TranslationFilters
{
    public function __construct(
        private readonly Translator $translator
    ) {}

    /**
     * Übersetzt einen Schlüssel
     */
    public function translate(mixed $value, array $parameters = []): string
    {
        if ($value === null) {
            return '';
        }

        $key = (string)$value;

        // Einfache Parameter-Struktur: ['param1' => 'value1', 'param2' => 'value2']
        $replacements = [];

        // Parameter können als Array oder einzelne Werte übergeben werden
        if (!empty($parameters)) {
            if (is_array($parameters[0] ?? null)) {
                // Erste Parameter ist ein Array mit Replacements
                $replacements = $parameters[0];
            } else {
                // Parameter sind einzelne Werte, konvertiere zu numerischen Platzhaltern
                foreach ($parameters as $index => $param) {
                    $replacements[':' . $index] = $param;
                }
            }
        }

        return $this->translator->translate($key, $replacements);
    }

    /**
     * Alias für translate
     */
    public function t(mixed $value, array $parameters = []): string
    {
        return $this->translate($value, $parameters);
    }

    /**
     * Übersetzt mit Pluralisierung
     */
    public function translatePlural(mixed $value, int $count, array $parameters = []): string
    {
        if ($value === null) {
            return '';
        }

        $key = (string)$value;

        // Count zu Replacements hinzufügen
        $replacements = ['count' => $count];

        // Zusätzliche Parameter hinzufügen
        if (!empty($parameters)) {
            if (is_array($parameters[0] ?? null)) {
                $replacements = array_merge($replacements, $parameters[0]);
            } else {
                foreach ($parameters as $index => $param) {
                    $replacements[':' . $index] = $param;
                }
            }
        }

        return $this->translator->translatePlural($key, $count, $replacements);
    }

    /**
     * Alias für translatePlural
     */
    public function tp(mixed $value, int $count, array $parameters = []): string
    {
        return $this->translatePlural($value, $count, $parameters);
    }

    /**
     * Prüft ob Übersetzung für Schlüssel existiert
     */
    public function hasTranslation(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $key = (string)$value;
        return $this->translator->has($key);
    }

    /**
     * Gibt aktuelle Locale zurück
     */
    public function locale(): string
    {
        return $this->translator->getLocale();
    }

    /**
     * Übersetzt Schlüssel in bestimmter Locale
     */
    public function translateIn(mixed $value, string $locale, array $parameters = []): string
    {
        if ($value === null) {
            return '';
        }

        $key = (string)$value;
        $currentLocale = $this->translator->getLocale();

        try {
            $this->translator->setLocale($locale);
            $result = $this->translate($key, $parameters);
        } finally {
            $this->translator->setLocale($currentLocale);
        }

        return $result;
    }
}