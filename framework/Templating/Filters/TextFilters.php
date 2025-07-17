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
    public static function truncate(mixed $value, mixed $length = 100, string $suffix = '...'): string
    {
        if ($value === null) {
            return '';
        }

        $stringValue = (string)$value;
        $lengthInt = is_numeric($length) ? (int)$length : 100;

        if (mb_strlen($stringValue) <= $lengthInt) {
            return $stringValue;
        }

        return mb_substr($stringValue, 0, $lengthInt) . $suffix;
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
     *
     * WARNUNG: Nur für vertrauenswürdige Inhalte verwenden!
     * Verhindert automatisches HTML-Escaping
     */
    public static function raw(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Explizites HTML-Escaping mit verschiedenen Strategien
     *
     * Verfügbare Strategien: html, attr, js, css, url
     */
    public static function escape(mixed $value, string $strategy = 'html'): string
    {
        if ($value === null) {
            return '';
        }

        $stringValue = (string)$value;

        return match ($strategy) {
            'html' => htmlspecialchars(
                $stringValue,
                ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8',
                true
            ),
            'attr' => htmlspecialchars(
                $stringValue,
                ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8',
                true
            ),
            'js' => self::escapeJavaScript($stringValue),
            'css' => self::escapeCss($stringValue),
            'url' => rawurlencode($stringValue),
            default => htmlspecialchars(
                $stringValue,
                ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8',
                true
            ),
        };
    }

    /**
     * Alias für escape mit html-Strategie
     */
    public static function e(mixed $value): string
    {
        return self::escape($value, 'html');
    }

    /**
     * JavaScript-String escaping
     */
    private static function escapeJavaScript(string $value): string
    {
        $escaped = json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        return $escaped !== false ? $escaped : '""';
    }

    /**
     * CSS-String escaping
     */
    private static function escapeCss(string $value): string
    {
        // Entferne gefährliche CSS-Zeichen
        return preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $value);
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
    public static function repeat(mixed $value, mixed $times): string
    {
        if ($value === null) {
            return '';
        }

        $timesInt = is_numeric($times) ? (int)$times : 0;

        if ($timesInt <= 0) {
            return '';
        }

        return str_repeat((string)$value, $timesInt);
    }

    /**
     * Kehrt String um
     */
    public static function reverse(mixed $value): string
    {
        return $value === null ? '' : strrev((string)$value);
    }
}