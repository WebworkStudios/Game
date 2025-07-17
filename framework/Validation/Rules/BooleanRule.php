<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * BooleanRule - Field must be boolean or boolean-like value
 */
class BooleanRule implements RuleInterface
{
    private const array BOOLEAN_VALUES = [
        true, false, 1, 0, '1', '0', 'true', 'false', 'on', 'off', 'yes', 'no'
    ];

    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        return $value === null || in_array($value, self::BOOLEAN_VALUES, true);
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} field must be true or false.";
    }
}