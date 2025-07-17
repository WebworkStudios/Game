<?php


declare(strict_types=1);

namespace Framework\Templating\Filters;

use DateTime;
use DateTimeInterface;
use Exception;

/**
 * DateFilters - Datum-Formatierung Filter
 *
 * Alle Filter sind NULL-safe
 */
class DateFilters
{
    /**
     * Formatiert Datum
     */
    public static function date(mixed $value, string $format = 'Y-m-d'): string
    {
        if ($value === null) {
            return '';
        }

        try {
            $date = self::createDateTime($value);
            return $date->format($format);
        } catch (Exception) {
            return '';
        }
    }

    /**
     * Formatiert als deutsche Datum
     */
    public static function dateGerman(mixed $value): string
    {
        return self::date($value, 'd.m.Y');
    }

    /**
     * Formatiert als Datum mit Uhrzeit
     */
    public static function datetime(mixed $value, string $format = 'Y-m-d H:i:s'): string
    {
        return self::date($value, $format);
    }

    /**
     * Formatiert als deutsche Datum mit Uhrzeit
     */
    public static function datetimeGerman(mixed $value): string
    {
        return self::date($value, 'd.m.Y H:i');
    }

    /**
     * Formatiert nur die Uhrzeit
     */
    public static function time(mixed $value, string $format = 'H:i:s'): string
    {
        return self::date($value, $format);
    }

    /**
     * Gibt relative Zeit zurück (vor x Minuten, etc.)
     */
    public static function timeAgo(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        try {
            $date = self::createDateTime($value);
            $now = new DateTime();
            $diff = $now->diff($date);

            if ($diff->invert === 0) {
                return 'in der Zukunft';
            }

            if ($diff->y > 0) {
                return $diff->y === 1 ? 'vor 1 Jahr' : "vor {$diff->y} Jahren";
            }

            if ($diff->m > 0) {
                return $diff->m === 1 ? 'vor 1 Monat' : "vor {$diff->m} Monaten";
            }

            if ($diff->d > 0) {
                return $diff->d === 1 ? 'vor 1 Tag' : "vor {$diff->d} Tagen";
            }

            if ($diff->h > 0) {
                return $diff->h === 1 ? 'vor 1 Stunde' : "vor {$diff->h} Stunden";
            }

            if ($diff->i > 0) {
                return $diff->i === 1 ? 'vor 1 Minute' : "vor {$diff->i} Minuten";
            }

            return 'vor wenigen Sekunden';
        } catch (Exception) {
            return '';
        }
    }

    /**
     * Gibt Tag der Woche zurück
     */
    public static function dayOfWeek(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        try {
            $date = self::createDateTime($value);
            $dayNames = [
                'Sunday' => 'Sonntag',
                'Monday' => 'Montag',
                'Tuesday' => 'Dienstag',
                'Wednesday' => 'Mittwoch',
                'Thursday' => 'Donnerstag',
                'Friday' => 'Freitag',
                'Saturday' => 'Samstag'
            ];

            $englishDay = $date->format('l');
            return $dayNames[$englishDay] ?? $englishDay;
        } catch (Exception) {
            return '';
        }
    }

    /**
     * Gibt Monat als Text zurück
     */
    public static function monthName(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        try {
            $date = self::createDateTime($value);
            $monthNames = [
                'January' => 'Januar',
                'February' => 'Februar',
                'March' => 'März',
                'April' => 'April',
                'May' => 'Mai',
                'June' => 'Juni',
                'July' => 'Juli',
                'August' => 'August',
                'September' => 'September',
                'October' => 'Oktober',
                'November' => 'November',
                'December' => 'Dezember'
            ];

            $englishMonth = $date->format('F');
            return $monthNames[$englishMonth] ?? $englishMonth;
        } catch (Exception) {
            return '';
        }
    }

    /**
     * Gibt Unix-Timestamp zurück
     */
    public static function timestamp(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        try {
            $date = self::createDateTime($value);
            return $date->getTimestamp();
        } catch (Exception) {
            return 0;
        }
    }

    /**
     * Hilfsmethode: Erstellt DateTime aus verschiedenen Formaten
     */
    private static function createDateTime(mixed $value): DateTime
    {
        if ($value instanceof DateTimeInterface) {
            return DateTime::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTime($value);
        }

        if (is_int($value)) {
            return new DateTime('@' . $value);
        }

        throw new Exception('Invalid date format');
    }
}