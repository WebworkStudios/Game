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

        // Format-Parameter optional berücksichtigen
        $format = $parameters[0] ?? null;

        if ($format !== null) {
            $date = \DateTime::createFromFormat($format, $value);
            return $date !== false && $date->format($format) === $value;
        }

        // Standard-Date-Validierung
        return strtotime($value) !== false;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        $format = $parameters[0] ?? 'valid date';
        return "The {$field} does not match the format {$format}.";
    }
}
