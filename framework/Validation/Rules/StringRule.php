<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * StringRule - Field must be a string
 *
 * MODERNISIERUNGEN PHP 8.4:
 * ✅ Match expression für type checking
 * ✅ String conversion validation (optional)
 * ✅ Unicode-aware string handling
 * ✅ Object __toString() support
 * ✅ Configurable strict mode
 * ✅ Better type safety
 */
class StringRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        // Null-safety: nullable fields erlauben
        if ($value === null) {
            return true;
        }

        // Check für strict mode parameter
        $strictMode = in_array('strict', $parameters, true);

        // String validation mit verschiedenen Modi
        return match (true) {
            // Native strings sind immer valid
            is_string($value) => true,

            // Strict mode: Nur native strings erlauben
            $strictMode => false,

            // Non-strict: Numeric values als strings behandeln
            is_numeric($value) => true,

            // Boolean zu string conversion
            is_bool($value) => true,

            // Objects mit __toString() method
            is_object($value) && method_exists($value, '__toString') => $this->isValidStringObject($value),

            // Alle anderen Typen sind invalid
            default => false
        };
    }

    /**
     * Validiert Object mit __toString() method
     */
    private function isValidStringObject(object $object): bool
    {
        try {
            // Versuche __toString() aufzurufen
            $stringValue = (string) $object;

            // Prüfe ob das Ergebnis ein gültiger String ist
            return is_string($stringValue);

        } catch (\Throwable $e) {
            // __toString() kann exceptions werfen
            return false;
        }
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        // Context-specific error messages
        $strictMode = in_array('strict', $parameters, true);

        return match (true) {
            $strictMode => "The {$field} must be a string (strict mode).",
            is_object($value) => "The {$field} must be a string, object given.",
            is_array($value) => "The {$field} must be a string, array given.",
            default => "The {$field} must be a string."
        };
    }
}