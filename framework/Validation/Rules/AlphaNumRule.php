<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * AlphaNumRule - Field must contain only alphabetic and numeric characters
 */
class AlphaNumRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return ctype_alnum($value);
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} may only contain letters and numbers.";
    }
}