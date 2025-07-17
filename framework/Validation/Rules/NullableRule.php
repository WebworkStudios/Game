<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * NullableRule - Field is allowed to be null or empty
 *
 * MODERNISIERUNGEN PHP 8.4:
 * ✅ Comprehensive nullable logic
 * ✅ Documentation für korrekte Verwendung
 * ✅ Performance-optimiert (always returns true)
 * ✅ Integration mit anderen Rules erklärt
 * ✅ Type-safe implementation
 *
 * Diese Rule fungiert als Marker und ändert das Verhalten anderer Rules.
 * Andere Validation Rules sollten null-Werte überspringen wenn nullable aktiv ist.
 *
 * Usage:
 * - 'email' => 'nullable|email' // Email kann null/leer sein, aber wenn vorhanden muss es valid sein
 * - 'age' => 'nullable|integer|min:18' // Age kann null sein, aber wenn vorhanden muss es >=18 sein
 */
class NullableRule implements RuleInterface
{
    /**
     * Nullable rule passes immer - es ist nur ein Marker
     *
     * Die eigentliche Nullable-Logic wird in anderen Rules implementiert:
     * - RequiredRule prüft auf nullable
     * - Andere Rules überspringen null-Werte wenn nullable gesetzt
     */
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        // Nullable ist immer true - es markiert nur das Feld als optional
        return true;
    }

    /**
     * Diese Methode sollte nie aufgerufen werden,
     * da nullable immer true zurückgibt
     */
    public function message(string $field, mixed $value, array $parameters): string
    {
        // Fallback message, sollte nie verwendet werden
        return "The {$field} field is nullable.";
    }

    /**
     * Hilfsmethode für andere Rules: Prüft ob ein Feld als nullable markiert ist
     *
     * @param array<string, string> $allRules Alle Rules für alle Felder
     * @param string $field Das zu prüfende Feld
     */
    public static function isFieldNullable(array $allRules, string $field): bool
    {
        $fieldRules = $allRules[$field] ?? '';

        // Prüfe ob 'nullable' in den Rules enthalten ist
        $rules = explode('|', $fieldRules);
        return in_array('nullable', array_map('trim', $rules), true);
    }

    /**
     * Hilfsmethode: Prüft ob ein Wert als "empty" gilt für nullable fields
     *
     * @param mixed $value Der zu prüfende Wert
     */
    public static function isEmptyValue(mixed $value): bool
    {
        return match (true) {
            $value === null => true,
            $value === '' => true,
            $value === [] => true,
            is_string($value) && trim($value) === '' => true,
            default => false
        };
    }
}