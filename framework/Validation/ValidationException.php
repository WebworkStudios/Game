<?php
declare(strict_types=1);

namespace Framework\Validation;

use RuntimeException;

/**
 * ValidationException - Thrown when validation configuration is invalid
 */
class ValidationException extends RuntimeException
{
    public function __construct(string $message = 'Validation failed', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}