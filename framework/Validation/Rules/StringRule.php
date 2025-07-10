<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * StringRule - Field must be a string
 */
class StringRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true; // nullable by default, use required rule for non-null
        }

        return is_string($value);
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} must be a string.";
    }
}