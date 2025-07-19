<?php
declare(strict_types=1);

namespace Framework\Validation;

use Framework\Http\HttpStatus;
use Override;
use RuntimeException;

/**
 * ValidationFailedException - Exception für fehlgeschlagene Validierung
 *
 * MODERNISIERUNGEN:
 * ✅ Readonly properties (PHP 8.1+)
 * ✅ Constructor property promotion
 * ✅ Enum statt int für HTTP Status
 * ✅ Bessere Typdeklarationen
 */
class ValidationFailedException extends RuntimeException
{
    public function __construct(
        private readonly MessageBag $errors,
        string $message = 'The given data was invalid.',
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }

    /**
     * Validation Errors abrufen
     */
    public function getErrors(): MessageBag
    {
        return $this->errors;
    }

    /**
     * Errors als Array für JSON Response
     */
    public function getErrorsArray(): array
    {
        return $this->errors->toArray();
    }

    /**
     * HTTP Status Code abrufen
     */
    public function getHttpStatus(): HttpStatus
    {
        return HttpStatus::UNPROCESSABLE_ENTITY;
    }
}