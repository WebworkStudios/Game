<?php

declare(strict_types=1);

namespace Framework\Http;

use InvalidArgumentException;

/**
 * HTTP Response - Mutable Response Object
 *
 * Bereinigt: Alle deprecated static factory methods entfernt.
 * Verwende ResponseFactory für Response-Erstellung.
 */
class Response
{
    private const array DEFAULT_HEADERS = [
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    private array $headers;
    private string $body = '';
    private bool $sent = false;

    public function __construct(
        private HttpStatus $status = HttpStatus::OK,
        array              $headers = [],
        string             $body = '',
    )
    {
        $this->headers = array_merge(self::DEFAULT_HEADERS, $headers);
        $this->body = $body;
    }

    // Fluent Interface

    public function withStatus(HttpStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withoutHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    public function withBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function withContentType(string $contentType): self
    {
        return $this->withHeader('Content-Type', $contentType);
    }

    public function withCookie(
        string $name,
        string $value,
        int    $expire = 0,
        string $path = '/',
        string $domain = '',
        bool   $secure = false,
        bool   $httpOnly = true,
        string $sameSite = 'Lax'
    ): self
    {
        if (headers_sent()) {
            throw new InvalidArgumentException('Cannot set cookie: headers already sent');
        }

        setcookie($name, $value, [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ]);

        return $this;
    }

    public function withCookieDeleted(string $name, string $path = '/', string $domain = ''): self
    {
        return $this->withCookie($name, '', time() - 3600, $path, $domain);
    }

    // Getters

    public function getStatus(): HttpStatus
    {
        return $this->status;
    }

    public function getStatusCode(): int
    {
        return $this->status->value;
    }

    public function getStatusText(): string
    {
        return $this->status->getText();
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function isSent(): bool
    {
        return $this->sent;
    }

    public function getContentType(): string
    {
        return $this->getHeader('Content-Type') ?? 'text/html; charset=UTF-8';
    }

    // Status Checks

    public function isSuccessful(): bool
    {
        return $this->status->isSuccess();
    }

    public function isError(): bool
    {
        return $this->status->isError();
    }

    public function isJson(): bool
    {
        return str_contains($this->getContentType(), 'application/json');
    }

    /**
     * Sendet Response an Client
     */
    public function send(): void
    {
        if ($this->sent) {
            throw new InvalidArgumentException('Response has already been sent');
        }

        if (headers_sent($file, $line)) {
            throw new InvalidArgumentException("Headers already sent in {$file} on line {$line}");
        }

        // Status Code senden
        http_response_code($this->status->value);

        // Headers senden
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Body senden (außer bei HEAD-Requests und Status Codes ohne Body)
        if ($this->status->allowsBody() &&
            ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo $this->body;
        }

        $this->sent = true;
    }
}