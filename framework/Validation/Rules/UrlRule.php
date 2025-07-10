<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * UrlRule - Field must be a valid URL
 */
class UrlRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        // Use PHP's built-in URL validation
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} must be a valid URL.";
    }
}