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

        return json_validate($value);
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} must be a valid JSON string.";
    }
}
