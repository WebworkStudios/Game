<?php
declare(strict_types=1);

namespace Framework\Templating\Filters;

use Framework\Templating\Utils\JsonUtility;

/**
 * UtilityFilters - Modernisierte Utility/Helper Filter
 *
 * UPDATED: Nutzt JsonUtility für moderne JSON-Verarbeitung
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
     * MODERNISIERT: JSON-Konvertierung mit JsonUtility
     */
    public static function json(mixed $value, bool $prettyPrint = false): string
    {
        return JsonUtility::forTemplate($value, $prettyPrint);
    }

    /**
     * HINZUGEFÜGT: JSON-Validierung
     */
    public static function isJson(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return JsonUtility::isValid($value);
    }

    /**
     * HINZUGEFÜGT: JSON zu Array/Object
     */
    public static function fromJson(mixed $value, mixed $fallback = []): mixed
    {
        if (!is_string($value)) {
            return $fallback;
        }

        return JsonUtility::safedecode($value, $fallback);
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
     * ERWEITERT: Pluralisierung mit besserer deutscher Grammatik
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

        // Verbesserte deutsche Pluralregeln
        return match (true) {
            str_ends_with($singular, 'e') => $singular . 'n',
            str_ends_with($singular, 'er') => $singular,
            str_ends_with($singular, 'el') => $singular,
            str_ends_with($singular, 's') => $singular,
            str_ends_with($singular, 'x') => $singular,
            str_ends_with($singular, 'z') => $singular,
            default => $singular . $pluralSuffix
        };
    }

    /**
     * HINZUGEFÜGT: Sicherer Array-Zugriff
     */
    public static function arrayGet(mixed $array, string $key, mixed $default = null): mixed
    {
        if (!is_array($array)) {
            return $default;
        }

        return $array[$key] ?? $default;
    }

    /**
     * HINZUGEFÜGT: Objekt-Property-Zugriff
     */
    public static function objectGet(mixed $object, string $property, mixed $default = null): mixed
    {
        if (!is_object($object)) {
            return $default;
        }

        return $object->$property ?? $default;
    }

    /**
     * HINZUGEFÜGT: Type-safe Casting
     */
    public static function toInt(mixed $value, int $default = 0): int
    {
        if ($value === null) {
            return $default;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        return $default;
    }

    /**
     * HINZUGEFÜGT: Type-safe Float-Casting
     */
    public static function toFloat(mixed $value, float $default = 0.0): float
    {
        if ($value === null) {
            return $default;
        }

        if (is_numeric($value)) {
            return (float)$value;
        }

        return $default;
    }

    /**
     * HINZUGEFÜGT: Type-safe Boolean-Casting
     */
    public static function toBool(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        // Spezielle String-Behandlung für Templates
        if (is_string($value)) {
            return !in_array(strtolower($value), ['false', '0', '', 'no', 'off', 'null']);
        }

        return (bool)$value;
    }

    /**
     * HINZUGEFÜGT: Range-Funktion für Templates
     */
    public static function range(int $start, int $end, int $step = 1): array
    {
        if ($step <= 0) {
            return [];
        }

        return range($start, $end, $step);
    }
}