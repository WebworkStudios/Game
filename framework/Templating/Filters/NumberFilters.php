<?php

declare(strict_types=1);

namespace Framework\Templating\Filters;

/**
 * NumberFilters - Zahlen-Formatierung Filter
 *
 * Alle Filter sind NULL-safe
 */
class NumberFilters
{
    /**
     * Formatiert als Währung
     */
    public static function currency(mixed $value, string $currency = 'EUR', string $locale = 'de_DE'): string
    {
        if ($value === null) {
            return '0,00 ' . $currency;
        }

        $numericValue = is_numeric($value) ? (float)$value : 0.0;
        $formatted = self::numberFormat($numericValue, 2);

        return $formatted . ' ' . $currency;
    }

    /**
     * Formatiert Zahlen mit Tausender-Trennzeichen
     */
    public static function numberFormat(mixed $value, mixed $decimals = 0, string $decimalSeparator = ',', string $thousandsSeparator = '.'): string
    {
        if ($value === null) {
            return '0';
        }

        $numericValue = is_numeric($value) ? (float)$value : 0.0;
        $decimalsInt = is_numeric($decimals) ? (int)$decimals : 0;

        return number_format($numericValue, $decimalsInt, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * Formatiert als Prozent
     */
    public static function percent(mixed $value, mixed $decimals = 1): string
    {
        if ($value === null) {
            return '0%';
        }

        $numericValue = is_numeric($value) ? (float)$value : 0.0;
        $decimalsInt = is_numeric($decimals) ? (int)$decimals : 1;
        $formatted = self::numberFormat($numericValue * 100, $decimalsInt);

        return $formatted . '%';
    }

    /**
     * Rundet Zahlen
     */
    public static function round(mixed $value, mixed $precision = 0): float
    {
        if ($value === null) {
            return 0.0;
        }

        $numericValue = is_numeric($value) ? (float)$value : 0.0;
        $precisionInt = is_numeric($precision) ? (int)$precision : 0;

        return round($numericValue, $precisionInt);
    }

    /**
     * Rundet nach oben
     */
    public static function ceil(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $numericValue = is_numeric($value) ? (float)$value : 0.0;
        return ceil($numericValue);
    }

    /**
     * Rundet nach unten
     */
    public static function floor(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $numericValue = is_numeric($value) ? (float)$value : 0.0;
        return floor($numericValue);
    }

    /**
     * Absoluter Wert
     */
    public static function abs(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $numericValue = is_numeric($value) ? (float)$value : 0.0;
        return abs($numericValue);
    }

    /**
     * Formatiert Bytes in lesbare Einheiten
     */
    public static function fileSize(mixed $value, mixed $precision = 2): string
    {
        if ($value === null) {
            return '0 B';
        }

        $bytes = is_numeric($value) ? (float)$value : 0.0;
        $precisionInt = is_numeric($precision) ? (int)$precision : 2;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return self::numberFormat($bytes, $precisionInt) . ' ' . $units[$i];
    }

    /**
     * Formatiert als Ordnungszahl (1st, 2nd, 3rd, etc.)
     */
    public static function ordinal(mixed $value): string
    {
        if ($value === null) {
            return '0';
        }

        $number = is_numeric($value) ? (int)$value : 0;

        if ($number <= 0) {
            return (string)$number;
        }

        $suffix = 'th';
        if ($number % 100 < 11 || $number % 100 > 13) {
            $suffix = match ($number % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th'
            };
        }

        return $number . $suffix;
    }
}