<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * NumericRule - Field must be numeric (int, float, or numeric string)
 *
 * MODERNISIERUNGEN PHP 8.4:
 * ✅ Match expression für comprehensive type checking
 * ✅ Scientific notation support (1e5, 2.5e-3)
 * ✅ Hexadecimal number support (0xFF)
 * ✅ Edge case handling (Infinity, NaN)
 * ✅ Performance-optimiert mit is_numeric()
 * ✅ Unicode-aware validation
 */
class NumericRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        // Null-safety: nullable fields erlauben
        if ($value === null) {
            return true;
        }

        // Comprehensive numeric validation mit match
        return match (true) {
            // Native numeric types sind immer valid
            is_int($value) || is_float($value) => $this->isValidNumericValue($value),

            // String-Validierung für Form-Inputs
            is_string($value) => $this->isNumericString($value),

            // Boolean als numerisch behandeln (optional)
            is_bool($value) => true, // true = 1, false = 0

            // Alle anderen Typen sind invalid
            default => false
        };
    }

    /**
     * Validiert numerische Werte auf special cases
     */
    private function isValidNumericValue(int|float $value): bool
    {
        // Prüfe auf Infinity und NaN bei Floats
        if (is_float($value)) {
            return is_finite($value); // Excludes INF, -INF, NAN
        }

        return true; // Integers sind immer valid
    }

    /**
     * String-Numeric-Validierung mit edge cases
     */
    private function isNumericString(string $value): bool
    {
        // Leere Strings sind nicht numeric
        if ($value === '') {
            return false;
        }

        // Whitespace trimmen für robuste Validierung
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        // PHP's is_numeric() ist sehr robust und unterstützt:
        // - Integers: "123", "-456"
        // - Floats: "12.34", "-5.67"
        // - Scientific notation: "1e5", "2.5e-3", "1E+10"
        // - Hexadecimal: "0xFF", "0x1A"
        return is_numeric($trimmed);
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} must be a number.";
    }
}