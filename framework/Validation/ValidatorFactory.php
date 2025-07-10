<?php

namespace Framework\Validation;

use Framework\Database\ConnectionManager;

/**
 * ValidatorFactory - Type-safe factory for creating validators
 */
readonly class ValidatorFactory
{
    public function __construct(
        private ConnectionManager $connectionManager
    )
    {
    }

    /**
     * Create validator instance
     */
    public function make(array $data, array $rules, ?string $connectionName = null): Validator
    {
        return Validator::make($data, $rules, $this->connectionManager);
    }
}