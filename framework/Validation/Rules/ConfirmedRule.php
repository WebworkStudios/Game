<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * ConfirmedRule - Field must have matching confirmation field
 *
 * MODERNISIERUNGEN PHP 8.4:
 * ✅ Modern string interpolation für field names
 * ✅ Strikte Type-Vergleiche mit match
 * ✅ Array-access mit null-coalescing
 * ✅ Performance-optimiert
 * ✅ Bessere Dokumentation
 *
 * Usage: confirmed
 * Example:
 * - 'password' => 'confirmed' looks for 'password_confirmation'
 * - 'email' => 'confirmed' looks for 'email_confirmation'
 */
class ConfirmedRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        // Null-safety: Wenn Hauptfeld null ist, ist confirmation nicht nötig
        if ($value === null) {
            return true;
        }

        // Confirmation field name generieren
        $confirmationField = "{$field}_confirmation";
        $confirmationValue = $data[$confirmationField] ?? null;

        // Strikte Vergleiche für verschiedene Typen
        return match (true) {
            // Beide null/leer
            $value === null && $confirmationValue === null => true,
            $value === '' && $confirmationValue === '' => true,

            // String-Vergleich (häufigster Fall)
            is_string($value) && is_string($confirmationValue) => $value === $confirmationValue,

            // Numerische Vergleiche
            is_numeric($value) && is_numeric($confirmationValue) => (string)$value === (string)$confirmationValue,

            // Array-Vergleich (falls nötig)
            is_array($value) && is_array($confirmationValue) => $value === $confirmationValue,

            // Fallback: Strikte Gleichheit
            default => $value === $confirmationValue
        };
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} confirmation does not match.";
    }
}