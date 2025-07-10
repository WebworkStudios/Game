<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * NullableRule - Field is allowed to be null or empty
 *
 * This rule doesn't actually validate anything - it just marks a field as nullable.
 * Other validation rules should check for null values and skip validation if the field is nullable.
 */
class NullableRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        // Nullable rule always passes - it's just a marker
        return true;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        // This should never be called since nullable always passes
        return "The {$field} field is nullable.";
    }
}