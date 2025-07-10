<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * AlphaRule - Field must contain only alphabetic characters
 */
class AlphaRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return ctype_alpha($value);
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} may only contain letters.";
    }
}