<?php
declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * RuleInterface - Contract for validation rules
 */
interface RuleInterface
{
    /**
     * Determine if the validation rule passes
     */
    public function passes(string $field, mixed $value, array $parameters, array $data): bool;

    /**
     * Get validation error message
     */
    public function message(string $field, mixed $value, array $parameters): string;
}