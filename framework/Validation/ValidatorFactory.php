<?php

namespace Framework\Validation;

use Framework\Database\ConnectionManager;

/**
 * ValidatorFactory - Type-safe factory for creating validators with custom messages support
 */
readonly class ValidatorFactory
{
    public function __construct(
        private ConnectionManager $connectionManager
    )
    {
    }

    /**
     * Create validator instance with custom messages support
     */
    public function make(
        array   $data,
        array   $rules,
        array   $customMessages = [],
        ?string $connectionName = null
    ): Validator
    {
        return Validator::make($data, $rules, $customMessages, $this->connectionManager);
    }
}