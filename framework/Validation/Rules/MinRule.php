<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

use InvalidArgumentException;

/**
 * MinRule - Field must meet minimum value/length requirements
 *
 * MODERNISIERUNGEN PHP 8.4:
 * ✅ Match expressions für type-specific validation
 * ✅ Parameter-Validierung mit early throws
 * ✅ Unicode-safe string length mit mb_strlen
 * ✅ Type-safe numeric comparisons
 * ✅ Consistent error messages
 * ✅ Array count validation
 * ✅ File size validation support
 */
class MinRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        // Null-safety: nullable fields erlauben
        if ($value === null) {
            return true;
        }

        // Parameter-Validierung
        if ($parameters === []) {
            throw new InvalidArgumentException(
                'MinRule requires a minimum value parameter. Usage: min:5'
            );
        }

        $min = $this->parseMinValue($parameters[0]);

        // Type-specific validation mit match
        return match (true) {
            // Numerische Werte (int, float, numeric strings)
            is_numeric($value) => $this->validateNumericMin($value, $min),

            // String-Length-Validierung (Unicode-safe)
            is_string($value) => $this->validateStringMin($value, $min),

            // Array-Count-Validierung
            is_array($value) => $this->validateArrayMin($value, $min),

            // File-Upload-Validierung (falls array mit size key)
            $this->isFileUpload($value) => $this->validateFileMin($value, $min),

            // Unbekannte Typen sind invalid
            default => false
        };
    }

    /**
     * Min-Parameter parsen und validieren
     */
    private function parseMinValue(string $minParam): int|float
    {
        if (!is_numeric($minParam)) {
            throw new InvalidArgumentException(
                "MinRule parameter must be numeric, got: {$minParam}"
            );
        }

        return str_contains($minParam, '.') ? (float) $minParam : (int) $minParam;
    }

    /**
     * Numerische Min-Validierung
     */
    private function validateNumericMin(mixed $value, int|float $min): bool
    {
        $numericValue = is_string($value) ? (float) $value : $value;
        return $numericValue >= $min;
    }

    /**
     * String-Length Min-Validierung (Unicode-safe)
     */
    private function validateStringMin(string $value, int|float $min): bool
    {
        $length = mb_strlen($value, 'UTF-8');
        return $length >= $min;
    }

    /**
     * Array-Count Min-Validierung
     */
    private function validateArrayMin(array $value, int|float $min): bool
    {
        return count($value) >= $min;
    }

    /**
     * Prüft ob Wert ein File-Upload ist
     */
    private function isFileUpload(mixed $value): bool
    {
        return is_array($value)
            && isset($value['size'])
            && isset($value['tmp_name']);
    }

    /**
     * File-Size Min-Validierung (in bytes)
     */
    private function validateFileMin(array $file, int|float $min): bool
    {
        $fileSize = $file['size'] ?? 0;
        return $fileSize >= $min;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        $min = $parameters[0];

        // Context-specific error messages
        return match (true) {
            is_numeric($value) => "The {$field} must be at least {$min}.",
            is_string($value) => "The {$field} must be at least {$min} characters.",
            is_array($value) && !$this->isFileUpload($value) => "The {$field} must have at least {$min} items.",
            $this->isFileUpload($value) => "The {$field} must be at least {$min} bytes.",
            default => "The {$field} must be at least {$min}."
        };
    }
}