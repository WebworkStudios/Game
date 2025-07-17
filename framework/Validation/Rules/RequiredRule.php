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
        return match (true) {
            $value === null => false,
            is_string($value) && trim($value) === '' => false,
            is_array($value) && $value === [] => false,
            default => true
        };
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} field is required.";
    }
}