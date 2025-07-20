<?php
declare(strict_types=1);

namespace Framework\Http;
/**
 * HttpStatus - PHP 8.4 Typed Constants Enum
 */
enum HttpStatus: int
{
    // Success
    case OK = 200;
    case CREATED = 201;
    case ACCEPTED = 202;
    case NO_CONTENT = 204;

    // Redirection
    case MOVED_PERMANENTLY = 301;
    case FOUND = 302;
    case NOT_MODIFIED = 304;

    // Client Error
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case UNPROCESSABLE_ENTITY = 422;

    // Server Error
    case INTERNAL_SERVER_ERROR = 500;
    case NOT_IMPLEMENTED = 501;
    case BAD_GATEWAY = 502;
    case SERVICE_UNAVAILABLE = 503;

    // PHP 8.4: Typed constant for status messages
    public const array STATUS_MESSAGES = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error'
    ];

    /**
     * Get HTTP Status Message
     */
    public function getMessage(): string
    {
        return self::STATUS_MESSAGES[$this->value] ?? 'Unknown Status';
    }

    /**
     * Check if status is successful (2xx)
     */
    public function isSuccessful(): bool
    {
        return $this->value >= 200 && $this->value < 300;
    }

    /**
     * Check if status is client error (4xx)
     */
    public function isClientError(): bool
    {
        return $this->value >= 400 && $this->value < 500;
    }

    /**
     * Check if status is server error (5xx)
     */
    public function isServerError(): bool
    {
        return $this->value >= 500 && $this->value < 600;
    }
}
