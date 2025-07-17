<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

/**
 * UtilityFilters - Utility/Helper Filter
 *
 * Alle Filter sind NULL-safe
 */
class UtilityFilters
{
    /**
     * Alias für length
     */
    public static function count(mixed $value): int
    {
        return self::length($value);
    }

    /**
     * Gibt die Länge von String oder Array zurück
     */
    public static function length(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }

        return mb_strlen((string)$value);
    }

    /**
     * Gibt das erste Element zurück
     */
    public static function first(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return reset($value) ?: null;
        }

        if (is_string($value)) {
            return mb_substr($value, 0, 1);
        }

        return $value;
    }

    /**
     * Gibt das letzte Element zurück
     */
    public static function last(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return end($value) ?: null;
        }

        if (is_string($value)) {
            return mb_substr($value, -1);
        }

        return $value;
    }

    /**
     * Konvertiert zu JSON
     */
    public static function json(mixed $value, int $flags = JSON_UNESCAPED_UNICODE): string
    {
        if ($value === null) {
            return 'null';
        }

        $json = json_encode($value, $flags);
        return $json !== false ? $json : 'null';
    }

    /**
     * Prüft ob Wert leer ist
     */
    public static function isEmpty(mixed $value): bool
    {
        return empty($value);
    }

    /**
     * Prüft ob Wert nicht leer ist
     */
    public static function isNotEmpty(mixed $value): bool
    {
        return !empty($value);
    }

    /**
     * Gibt Typ des Werts zurück
     */
    public static function type(mixed $value): string
    {
        return gettype($value);
    }

    /**
     * Pluralisierung (einfache deutsche Regel)
     */
    public static function plural(mixed $value, mixed $count, string $pluralSuffix = 'e'): string
    {
        if ($value === null) {
            return '';
        }

        $singular = (string)$value;
        $countInt = is_numeric($count) ? (int)$count : 0;

        if ($countInt === 1) {
            return $singular;
        }

        // Einfache deutsche Pluralregeln
        if (str_ends_with($singular, 'e')) {
            return $singular . 'n';
        }

        if (str_ends_with($singular, 'er')) {
            return $singular;
        }

        if (str_ends_with($singular, 'el')) {
            return $singular;
        }

        return $singular . $pluralSuffix;
    }

    /**
     * Sortiert Array
     */
    public static function sort(mixed $value, bool $reverse = false): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sorted = $value;

        if ($reverse) {
            rsort($sorted);
        } else {
            sort($sorted);
        }

        return $sorted;
    }

    /**
     * Gibt zufälliges Element zurück
     */
    public static function random(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            if (empty($value)) {
                return null;
            }
            return $value[array_rand($value)];
        }

        if (is_string($value)) {
            $length = mb_strlen($value);
            if ($length === 0) {
                return '';
            }
            return mb_substr($value, random_int(0, $length - 1), 1);
        }

        return $value;
    }

    /**
     * Verbindet Array-Elemente mit Separator
     */
    public static function join(mixed $value, string $separator = ', '): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return implode($separator, $value);
        }

        return (string)$value;
    }

    /**
     * Teilt String in Array auf
     */
    public static function split(mixed $value, string $separator = ','): array
    {
        if ($value === null) {
            return [];
        }

        $stringValue = (string)$value;

        if ($stringValue === '') {
            return [];
        }

        return explode($separator, $stringValue);
    }

    /**
     * Entfernt Duplikate aus Array
     */
    public static function unique(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_unique($value);
    }
}