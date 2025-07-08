<?php


declare(strict_types=1);

namespace Framework\Http;

/**
 * HTTP-Methoden Enum
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

    /**
     * Prüft ob HTTP-Methode idempotent ist
     */
    public function isIdempotent(): bool
    {
        return match ($this) {
            self::GET, self::HEAD, self::OPTIONS, self::PUT, self::DELETE => true,
            self::POST, self::PATCH => false,
        };
    }

    /**
     * Prüft ob HTTP-Methode safe ist (keine Seiteneffekte)
     */
    public function isSafe(): bool
    {
        return match ($this) {
            self::GET, self::HEAD, self::OPTIONS => true,
            default => false,
        };
    }

    /**
     * Prüft ob Methode einen Request Body erlaubt
     */
    public function allowsBody(): bool
    {
        return match ($this) {
            self::POST, self::PUT, self::PATCH => true,
            self::GET, self::HEAD, self::DELETE, self::OPTIONS => false,
        };
    }

    /**
     * Prüft ob Methode cacheable ist
     */
    public function isCacheable(): bool
    {
        return match ($this) {
            self::GET, self::HEAD => true,
            default => false,
        };
    }
}