<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * EmailRule - Field must be a valid email address
 */
class EmailRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} must be a valid email address.";
    }
}