<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * IntegerRule - Field must be an integer or integer-like value
 *
 * MODERNISIERUNGEN PHP 8.4:
 * ✅ Match expression für type checking
 * ✅ Verbesserte Integer-Validierung mit edge cases
 * ✅ Support für string integers (form inputs)
 * ✅ Range validation considerations
 * ✅ Performance-optimiert mit filter_var
 * ✅ Bessere Documentation
 */
class IntegerRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        // Null-safety: nullable fields erlauben
        if ($value === null) {
            return true;
        }

        // Comprehensive integer validation mit match
        return match (true) {
            // Native integers sind immer valid
            is_int($value) => true,

            // Float-Werte prüfen ob sie ganze Zahlen sind
            is_float($value) => $this->isWholeNumber($value),

            // String-Werte validieren (häufig bei Form-Inputs)
            is_string($value) => $this->isIntegerString($value),

            // Boolean-Werte als 0/1 behandeln (optional)
            is_bool($value) => true, // true = 1, false = 0

            // Alle anderen Typen sind invalid
            default => false
        };
    }

    /**
     * Prüft ob Float-Wert eine ganze Zahl ist
     */
    private function isWholeNumber(float $value): bool
    {
        // Check für Infinite/NaN values
        if (!is_finite($value)) {
            return false;
        }

        // Prüfe ob Dezimalstellen vorhanden sind
        return floor($value) === $value;
    }

    /**
     * Validiert String als Integer mit edge cases
     */
    private function isIntegerString(string $value): bool
    {
        // Leere Strings sind nicht valid (außer bei nullable)
        if ($value === '') {
            return false;
        }

        // Whitespace trimmen für robuste Validierung
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        // filter_var mit FILTER_VALIDATE_INT für robuste Validierung
        $result = filter_var($trimmed, FILTER_VALIDATE_INT);

        // filter_var returns int on success, false on failure
        return $result !== false;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} must be an integer.";
    }
}