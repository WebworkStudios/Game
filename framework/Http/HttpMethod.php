<?php
declare(strict_types=1);

namespace Framework\Http;
/**
 * HttpMethod - PHP 8.4 Enhanced Enum
 */
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';

    // PHP 8.4: Typed constant for safe methods
    public const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    public const array IDEMPOTENT_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'];

    /**
     * Check if method is safe (read-only)
     */
    public function isSafe(): bool
    {
        return in_array($this->value, self::SAFE_METHODS, true);
    }

    /**
     * Check if method is idempotent
     */
    public function isIdempotent(): bool
    {
        return in_array($this->value, self::IDEMPOTENT_METHODS, true);
    }

    /**
     * Create from string with validation
     */
    public static function fromString(string $method): self
    {
        $normalized = strtoupper(trim($method));

        return match ($normalized) {
            'GET' => self::GET,
            'POST' => self::POST,
            'PUT' => self::PUT,
            'PATCH' => self::PATCH,
            'DELETE' => self::DELETE,
            'HEAD' => self::HEAD,
            'OPTIONS' => self::OPTIONS,
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}")
        };
    }
}