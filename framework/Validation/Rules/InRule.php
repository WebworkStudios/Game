<?php


declare(strict_types=1);

namespace Framework\Validation\Rules;

use InvalidArgumentException;

/**
 * InRule - Field value must be in given list
 *
 * Usage: in:value1,value2,value3
 * Example: in:admin,user,moderator
 */
class InRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if ($parameters === []) {
            throw new InvalidArgumentException('In rule requires at least one parameter');
        }

        return in_array((string) $value, $parameters, true);
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        $options = implode(', ', $parameters);
        return "The selected {$field} is invalid. Must be one of: {$options}.";
    }
}