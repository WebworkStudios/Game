<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

use InvalidArgumentException;

/**
 * RegexRule - Field must match given regular expression
 *
 * Usage: regex:/pattern/flags
 * Examples:
 * - regex:/^[A-Z]{2}[0-9]{4}$/ (2 letters + 4 digits)
 * - regex:/^\+?[1-9]\d{1,14}$/ (phone number)
 */
class RegexRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        if (empty($parameters)) {
            throw new InvalidArgumentException('Regex rule requires a pattern parameter');
        }

        $pattern = $parameters[0];

        // Validate regex pattern
        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException("Invalid regex pattern: {$pattern}");
        }

        return preg_match($pattern, $value) === 1;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} format is invalid.";
    }
}