<?php

namespace Framework\Validation;

/**
 * ValidatorFactory - Type-safe factory for creating validators
 */
class ValidatorFactory
{
    public function __construct(
        private readonly callable $factory
    )
    {
    }

    /**
     * Create validator instance
     */
    public function make(array $data, array $rules, ?string $connectionName = null): Validator
    {
        return ($this->factory)($data, $rules, $connectionName);
    }
}