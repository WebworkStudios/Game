<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * AlphaRule - Field must contain only alphabetic characters
 *
 * MODERNISIERUNGEN PHP 8.4:
 * ✅ Match expression für type checking
 * ✅ Strikte Typdeklarationen
 * ✅ Optimierte Performance mit ctype_alpha()
 * ✅ Null-safety mit early return
 * ✅ Konsistente Error-Messages
 */
class AlphaRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        // Null-safety: nullable fields erlauben
        if ($value === null) {
            return true;
        }

        // Type-safe validation mit match expression
        return match (true) {
            !is_string($value) => false,
            $value === '' => true, // Leere Strings sind erlaubt (required rule für non-empty)
            default => ctype_alpha($value)
        };
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} may only contain letters.";
    }
}