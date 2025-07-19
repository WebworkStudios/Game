<?php
declare(strict_types=1);

namespace Framework\Templating\Utils;

use JsonException;

/**
 * JsonUtility - Moderne JSON-Verarbeitung mit json_validate() (PHP 8.3+)
 *
 * Bietet sichere und performante JSON-Operationen für das Template-System.
 * Nutzt die neuen PHP 8.3+ Features für optimale Leistung.
 */
class JsonUtility
{
    /**
     * Validiert JSON-String ohne Parsing (super schnell)
     *
     * Nutzt json_validate() für bessere Performance als json_decode() + json_last_error()
     */
    public static function isValid(string $json): bool
    {
        return json_validate($json);
    }

    /**
     * Sichere JSON-Encodierung mit modernen Features
     */
    public static function encode(
        mixed $value,
        int $flags = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        int $depth = 512
    ): string {
        if ($value === null) {
            return 'null';
        }

        try {
            return json_encode($value, $flags, $depth);
        } catch (JsonException $e) {
            // Fallback für problematische Daten
            return json_encode([
                'error' => 'JSON encoding failed',
                'type' => gettype($value),
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Sichere JSON-Decodierung mit Validierung
     */
    public static function decode(
        string $json,
        bool $associative = true,
        int $depth = 512,
        int $flags = JSON_THROW_ON_ERROR
    ): mixed {
        // Schnelle Validierung vor dem Parsing
        if (!self::isValid($json)) {
            throw new JsonException("Invalid JSON string provided");
        }

        try {
            return json_decode($json, $associative, $depth, $flags);
        } catch (JsonException $e) {
            throw new JsonException("JSON decode failed: " . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Sichere JSON-Decodierung mit Fallback
     */
    public static function safedecode(string $json, mixed $fallback = null): mixed
    {
        try {
            return self::decode($json);
        } catch (JsonException) {
            return $fallback;
        }
    }

    /**
     * Pretty-Print JSON für Debug-Zwecke
     */
    public static function prettyEncode(mixed $value): string
    {
        return self::encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        );
    }

    /**
     * JSON für JavaScript-Templates (XSS-sicher)
     */
    public static function encodeForJavaScript(mixed $value): string
    {
        return self::encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS |
            JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Minimaler JSON (ohne Whitespace)
     */
    public static function encodeMinimal(mixed $value): string
    {
        return self::encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Prüft ob String wahrscheinlich JSON ist (heuristische Prüfung)
     */
    public static function looksLikeJson(string $value): bool
    {
        $trimmed = trim($value);

        if (empty($trimmed)) {
            return false;
        }

        // Schnelle Heuristik: JSON-typische Start/End-Zeichen
        $firstChar = $trimmed[0];
        $lastChar = $trimmed[-1];

        return match ($firstChar) {
            '{' => $lastChar === '}',
            '[' => $lastChar === ']',
            '"' => $lastChar === '"' && strlen($trimmed) >= 2,
            default => in_array($trimmed, ['true', 'false', 'null']) || is_numeric($trimmed)
        };
    }

    /**
     * Erweiterte JSON-Validierung mit Details
     */
    public static function validateWithDetails(string $json): array
    {
        if (!self::looksLikeJson($json)) {
            return [
                'valid' => false,
                'error' => 'String does not appear to be JSON format',
                'position' => null
            ];
        }

        if (function_exists('json_validate')) {
            $isValid = json_validate($json);

            if (!$isValid) {
                // Für detaillierte Fehlerinfo fallback zu json_decode
                json_decode($json);
                return [
                    'valid' => false,
                    'error' => json_last_error_msg(),
                    'position' => null // json_validate() gibt keine Position zurück
                ];
            }

            return ['valid' => true, 'error' => null, 'position' => null];
        }

        // Fallback für PHP < 8.3
        json_decode($json);
        $error = json_last_error();

        return [
            'valid' => $error === JSON_ERROR_NONE,
            'error' => $error !== JSON_ERROR_NONE ? json_last_error_msg() : null,
            'position' => null
        ];
    }

    /**
     * Template-spezifische JSON-Konvertierung für Filter
     */
    public static function forTemplate(mixed $value, bool $prettyPrint = false): string
    {
        if ($value === null) {
            return 'null';
        }

        // Spezielle Behandlung für Template-Variablen
        if (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            return json_encode($value, $flags);
        } catch (JsonException) {
            // Fallback für nicht-serialisierbare Objekte
            return json_encode([
                'type' => get_class($value) ?: gettype($value),
                'serializable' => false
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}