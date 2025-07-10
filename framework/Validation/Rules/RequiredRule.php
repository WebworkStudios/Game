<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * RequiredRule - Field must be present and not empty
 */
class RequiredRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} field is required.";
    }
}