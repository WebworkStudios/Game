<?php


declare(strict_types=1);

namespace Framework\Templating\Filters;

/**
 * TextFilters - Text-Manipulation Filter
 *
 * Alle Filter sind NULL-safe und geben leere Strings zurück bei null-Werten
 */
class TextFilters
{
    /**
     * Konvertiert zu Großbuchstaben
     */
    public static function upper(mixed $value): string
    {
        return $value === null ? '' : strtoupper((string)$value);
    }

    /**
     * Konvertiert zu Kleinbuchstaben
     */
    public static function lower(mixed $value): string
    {
        return $value === null ? '' : strtolower((string)$value);
    }

    /**
     * Kapitalisiert ersten Buchstaben
     */
    public static function capitalize(mixed $value): string
    {
        return $value === null ? '' : ucfirst(strtolower((string)$value));
    }

    /**
     * Kürzt Text auf bestimmte Länge
     */
    public static function truncate(mixed $value, int $length = 100, string $suffix = '...'): string
    {
        if ($value === null) {
            return '';
        }

        $stringValue = (string)$value;

        if (mb_strlen($stringValue) <= $length) {
            return $stringValue;
        }

        return mb_substr($stringValue, 0, $length) . $suffix;
    }

    /**
     * Erstellt URL-freundlichen Slug
     */
    public static function slug(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $stringValue = (string)$value;

        // Umlaute ersetzen
        $stringValue = strtr($stringValue, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue'
        ]);

        // Zu Kleinbuchstaben und nur alphanumerische Zeichen + Bindestriche
        $stringValue = strtolower($stringValue);
        $stringValue = preg_replace('/[^a-z0-9]+/', '-', $stringValue);
        $stringValue = trim($stringValue, '-');

        return $stringValue;
    }

    /**
     * Konvertiert Newlines zu <br> Tags
     */
    public static function nl2br(mixed $value): string
    {
        return $value === null ? '' : nl2br((string)$value);
    }

    /**
     * Entfernt HTML-Tags
     */
    public static function stripTags(mixed $value, string $allowedTags = ''): string
    {
        return $value === null ? '' : strip_tags((string)$value, $allowedTags);
    }

    /**
     * Gibt den Wert unverändert zurück (für raw-Output)
     */
    public static function raw(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Gibt Default-Wert zurück wenn ursprünglicher Wert null/leer ist
     */
    public static function default(mixed $value, mixed $default = ''): mixed
    {
        return $value ?? $default;
    }

    /**
     * Entfernt Whitespace am Anfang und Ende
     */
    public static function trim(mixed $value, string $characters = " \t\n\r\0\x0B"): string
    {
        return $value === null ? '' : trim((string)$value, $characters);
    }

    /**
     * Ersetzt Substrings
     */
    public static function replace(mixed $value, string $search, string $replace): string
    {
        return $value === null ? '' : str_replace($search, $replace, (string)$value);
    }

    /**
     * Wiederholt String x-mal
     */
    public static function repeat(mixed $value, int $times): string
    {
        if ($value === null || $times <= 0) {
            return '';
        }

        return str_repeat((string)$value, $times);
    }

    /**
     * Kehrt String um
     */
    public static function reverse(mixed $value): string
    {
        return $value === null ? '' : strrev((string)$value);
    }
}