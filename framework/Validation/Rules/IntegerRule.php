<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * IntegerRule - Field must be an integer
 */
class IntegerRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} must be an integer.";
    }
}