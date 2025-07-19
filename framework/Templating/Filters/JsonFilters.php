<?php
declare(strict_types=1);

namespace Framework\Templating\Filters;

use Framework\Templating\Utils\JsonUtility;

/**
 * JsonFilters - Modernisierte JSON-Filter mit json_validate()
 *
 * UPDATED: Nutzt JsonUtility für sichere und performante JSON-Operationen
 */
class JsonFilters
{
    /**
     * Standard JSON-Encodierung für Templates
     */
    public static function json(mixed $value, bool $prettyPrint = false): string
    {
        return JsonUtility::forTemplate($value, $prettyPrint);
    }

    /**
     * Pretty-Print JSON für Debug/Development
     */
    public static function jsonPretty(mixed $value): string
    {
        return JsonUtility::prettyEncode($value);
    }

    /**
     * JSON für JavaScript-Kontext (XSS-sicher)
     */
    public static function jsonJs(mixed $value): string
    {
        return JsonUtility::encodeForJavaScript($value);
    }

    /**
     * Minimaler JSON ohne Whitespace
     */
    public static function jsonMinimal(mixed $value): string
    {
        return JsonUtility::encodeMinimal($value);
    }

    /**
     * JSON-Validierung in Templates
     */
    public static function isJson(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return JsonUtility::isValid($value);
    }

    /**
     * JSON-String zu Array/Object decodieren
     */
    public static function fromJson(mixed $value, mixed $fallback = null): mixed
    {
        if (!is_string($value)) {
            return $fallback;
        }

        return JsonUtility::safedecode($value, $fallback);
    }

    /**
     * JSON-Validierung mit Fehlerdetails (für Debug)
     */
    public static function jsonValidate(mixed $value): array
    {
        if (!is_string($value)) {
            return [
                'valid' => false,
                'error' => 'Value is not a string',
                'type' => gettype($value)
            ];
        }

        $result = JsonUtility::validateWithDetails($value);
        return [
            'valid' => $result['valid'],
            'error' => $result['error'],
            'looks_like_json' => JsonUtility::looksLikeJson($value)
        ];
    }

    /**
     * Encode nur wenn noch kein JSON
     */
    public static function ensureJson(mixed $value): string
    {
        if (is_string($value) && JsonUtility::isValid($value)) {
            return $value; // Bereits JSON
        }

        return JsonUtility::forTemplate($value);
    }
}