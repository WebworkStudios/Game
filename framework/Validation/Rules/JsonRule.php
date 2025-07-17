<?php

declare(strict_types=1);

namespace Framework\Validation\Rules;

class JsonRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        // PHP 8.3+ json_validate() wenn verfügbar, sonst Fallback
        if (function_exists('json_validate')) {
            return json_validate($value);
        }

        // Fallback für ältere PHP-Versionen
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} must be a valid JSON string.";
    }
}
