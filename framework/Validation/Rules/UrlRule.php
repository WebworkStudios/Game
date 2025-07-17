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

        // Erweiterte Validierung basierend auf Parametern
        $flags = FILTER_VALIDATE_URL;

        if (in_array('strict', $parameters, true)) {
            $flags |= FILTER_FLAG_PATH_REQUIRED;
        }

        if (in_array('query', $parameters, true)) {
            $flags |= FILTER_FLAG_QUERY_REQUIRED;
        }

        return filter_var($value, $flags) !== false;
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        return "The {$field} must be a valid URL.";
    }
}
