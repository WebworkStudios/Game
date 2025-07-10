<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * ConfirmedRule - Field must have matching confirmation field
 *
 * Usage: confirmed
 * Example:
 * - 'password' => 'confirmed' looks for 'password_confirmation'
 * - 'email' => 'confirmed' looks for 'email_confirmation'
 */
class ConfirmedRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        $confirmationField = $field . '_confirmation';
        $confirmationValue = $data[$confirmationField] ?? null;

        return $value === $confirmationValue;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} confirmation does not match.";
    }
}