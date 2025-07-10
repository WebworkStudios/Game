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
        if ($value === null) {
            return true;
        }

        if (empty($parameters)) {
            throw new \InvalidArgumentException('Max rule requires a parameter');
        }

        $max = (int)$parameters[0];

        if (is_numeric($value)) {
            return (float)$value <= $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        $max = $parameters[0];

        if (is_numeric($value)) {
            return "The {$field} may not be greater than {$max}.";
        }

        return "The {$field} may not be greater than {$max} characters.";
    }
}