<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * MinRule - Field must have minimum value/length
 */
class MinRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (empty($parameters)) {
            throw new \InvalidArgumentException('Min rule requires a parameter');
        }

        $min = (int)$parameters[0];

        if (is_numeric($value)) {
            return (float)$value >= $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        $min = $parameters[0];

        if (is_numeric($value)) {
            return "The {$field} must be at least {$min}.";
        }

        return "The {$field} must be at least {$min} characters.";
    }
}