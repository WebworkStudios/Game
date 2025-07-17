<?php
declare(strict_types=1);

namespace Framework\Http;

/**
 * HTTP-Methoden Enum mit erweiterten Funktionen für PHP 8.4
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
    case TRACE = 'TRACE';    // HINZUGEFÜGT: Fehlende HTTP-Methode
    case CONNECT = 'CONNECT'; // HINZUGEFÜGT: Fehlende HTTP-Methode

    /**
     * NEU: Gibt alle verfügbaren Methoden als Array zurück
     */
    public static function all(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    /**
     * Prüft ob HTTP-Methode idempotent ist
     */
    public function isIdempotent(): bool
    {
        return match ($this) {
            self::GET, self::HEAD, self::OPTIONS, self::PUT, self::DELETE, self::TRACE => true,
            self::POST, self::PATCH, self::CONNECT => false,
        };
    }

    /**
     * Prüft ob HTTP-Methode safe ist (keine Seiteneffekte)
     */
    public function isSafe(): bool
    {
        return match ($this) {
            self::GET, self::HEAD, self::OPTIONS, self::TRACE => true,
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
            self::GET, self::HEAD, self::DELETE, self::OPTIONS, self::TRACE, self::CONNECT => false,
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

    /**
     * NEU: Erstellt sicheren HTTP-Method-String für Logs
     */
    public function toLogString(): string
    {
        return $this->value;
    }
}
