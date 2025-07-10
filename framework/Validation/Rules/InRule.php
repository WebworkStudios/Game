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

        if (empty($parameters)) {
            throw new InvalidArgumentException('In rule requires at least one parameter');
        }

        // Convert value to string for comparison (consistent with form inputs)
        $stringValue = (string)$value;

        return in_array($stringValue, $parameters, true);
    }

    public function message(string $field, mixed $value, array $parameters): string
    {
        $options = implode(', ', $parameters);
        return "The selected {$field} is invalid. Must be one of: {$options}.";
    }
}