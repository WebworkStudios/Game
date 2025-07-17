<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * MaxRule - Field must not exceed maximum value/length
 */
class MaxRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $parameters === []) {
            return true;
        }

        $max = (int) $parameters[0];

        return match (true) {
            is_numeric($value) => (float) $value <= $max,
            is_string($value) => mb_strlen($value) <= $max,
            is_array($value) => count($value) <= $max,
            default => false
        };
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        $max = $parameters[0];

        return is_numeric($value)
            ? "The {$field} may not be greater than {$max}."
            : "The {$field} may not be greater than {$max} characters.";
    }
}
