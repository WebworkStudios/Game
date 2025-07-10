<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * NumericRule - Field must be numeric (int, float, or numeric string)
 */
class NumericRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        return is_numeric($value);
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} must be a number.";
    }
}