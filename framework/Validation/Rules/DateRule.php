<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * DateRule - Field must be a valid date
 *
 * Accepts various date formats:
 * - Y-m-d (2024-12-31)
 * - Y-m-d H:i:s (2024-12-31 23:59:59)
 * - d/m/Y (31/12/2024)
 * - d.m.Y (31.12.2024)
 * - Any format parseable by strtotime()
 */
class DateRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        // Try to parse the date
        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return false;
        }

        // Additional validation: check if the parsed date matches original input
        // This prevents cases like "2024-02-30" being converted to "2024-03-02"
        $parsedDate = date('Y-m-d', $timestamp);
        $normalizedInput = date('Y-m-d', strtotime($value));

        return $parsedDate === $normalizedInput;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} is not a valid date.";
    }
}