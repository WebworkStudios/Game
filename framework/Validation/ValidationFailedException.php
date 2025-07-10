<?php


declare(strict_types=1);

namespace Framework\Validation;

use Framework\Http\HttpStatus;
use RuntimeException;

/**
 * ValidationFailedException - Thrown when validation fails
 */
class ValidationFailedException extends RuntimeException
{
    public function __construct(
        private readonly MessageBag $errors,
        string                      $message = 'The given data was invalid.',
        int                         $code = 422
    )
    {
        parent::__construct($message, $code);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): MessageBag
    {
        return $this->errors;
    }

    /**
     * Get errors as array for JSON response
     */
    public function getErrorsArray(): array
    {
        return $this->errors->toArray();
    }

    /**
     * Get HTTP status code
     */
    public function getHttpStatus(): HttpStatus
    {
        return HttpStatus::UNPROCESSABLE_ENTITY;
    }
}