<?php

declare(strict_types=1);

namespace Framework\Http;

use InvalidArgumentException;

/**
 * HTTP Response - Mutable Response Object
 *
 * BEREINIGT: Alle deprecated static factory methods entfernt.
 * Verwende ausschließlich ResponseFactory für Response-Erstellung.
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

    // ===================================================================
    // Fluent Interface Methods
    // ===================================================================

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

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withCookieDeleted(string $name, string $path = '/', string $domain = ''): self
    {
        return $this->withCookie($name, '', time() - 3600, $path, $domain);
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

    // ===================================================================
    // Getter Methods
    // ===================================================================

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

    // ===================================================================
    // Status Check Methods
    // ===================================================================

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isRedirect(): bool
    {
        return $this->status->isRedirect();
    }

    public function isClientError(): bool
    {
        return $this->status->isClientError();
    }

    public function isServerError(): bool
    {
        return $this->status->isServerError();
    }

    public function isInformational(): bool
    {
        return $this->status->isInformational();
    }

    // ===================================================================
    // Output Methods
    // ===================================================================

    /**
     * Sendet Response an Browser
     */
    public function send(): void
    {
        if ($this->sent) {
            throw new InvalidArgumentException('Response has already been sent');
        }

        $this->sendHeaders();
        $this->sendBody();
        $this->sent = true;
    }

    /**
     * Sendet HTTP Headers
     */
    private function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        // Status Line
        http_response_code($this->status->value);

        // Headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /**
     * Sendet Response Body
     */
    private function sendBody(): void
    {
        echo $this->body;
    }

    // ===================================================================
    // Debug Methods
    // ===================================================================

    public function __toString(): string
    {
        $headers = [];
        foreach ($this->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        return sprintf(
            "HTTP/1.1 %d %s\r\n%s\r\n\r\n%s",
            $this->status->value,
            $this->status->getText(),
            implode("\r\n", $headers),
            $this->body
        );
    }

    /**
     * Debug-Ausgabe der Response
     */
    public function dump(): void
    {
        echo "\n=== HTTP RESPONSE DEBUG ===\n";
        echo "Status: {$this->status->value} {$this->status->getText()}\n";
        echo "Headers:\n";
        foreach ($this->headers as $name => $value) {
            echo "  {$name}: {$value}\n";
        }
        echo "Body Length: " . strlen($this->body) . " bytes\n";
        echo "Body Preview: " . substr($this->body, 0, 100) . "...\n";
        echo "========================\n\n";
    }
}