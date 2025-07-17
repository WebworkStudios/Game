<?php

declare(strict_types=1);

namespace Framework\Http;

use InvalidArgumentException;
use JsonException;

/**
 * HTTP Response - Mutable Response Object mit PHP 8.4 Features
 */
class Response
{
    /**
     * MODERNISIERT: Typed Class Constants (PHP 8.3+)
     */
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
        $this->headers = [...self::DEFAULT_HEADERS, ...$headers]; // MODERNISIERT
        $this->body = $body;
    }

    // ===================================================================
    // Fluent Interface Methods - MODERNISIERT
    // ===================================================================

    public function withStatus(HttpStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function withoutHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    public function withCookieDeleted(string $name, string $path = '/', string $domain = ''): self
    {
        return $this->withCookie($name, '', time() - 3600, $path, $domain);
    }

    /**
     * MODERNISIERT: Cookie-Handling mit SameSite-Enum Support
     */
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

    public function getStatus(): HttpStatus
    {
        return $this->status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    // ===================================================================
    // Getter Methods - ERWEITERT
    // ===================================================================

    public function getBody(): string
    {
        return $this->body;
    }

    public function isSent(): bool
    {
        return $this->sent;
    }

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

    // ===================================================================
    // Status Check Methods - DELEGIERT AN ENUM
    // ===================================================================

    public function isInformational(): bool
    {
        return $this->status->isInformational();
    }

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
     * MODERNISIERT: Header-Sending mit besserer Prüfung
     */
    private function sendHeaders(): void
    {
        if (headers_sent($file, $line)) {
            throw new InvalidArgumentException(
                "Headers already sent in {$file}:{$line}"
            );
        }

        // Status Line
        http_response_code($this->status->value);

        // Headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    private function sendBody(): void
    {
        echo $this->body;
    }

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

    // ===================================================================
    // Output Methods - MODERNISIERT
    // ===================================================================

    /**
     * NEU: Debug-Ausgabe der Response mit mehr Details
     */
    public function dump(): void
    {
        echo "\n=== HTTP RESPONSE DEBUG ===\n";
        echo "Status: {$this->status->value} {$this->status->getText()}\n";
        echo "Sent: " . ($this->sent ? 'Yes' : 'No') . "\n";
        echo "Headers:\n";
        foreach ($this->headers as $name => $value) {
            echo "  {$name}: {$value}\n";
        }
        echo "Body Length: " . strlen($this->body) . " bytes\n";
        echo "Body Preview: " . substr($this->body, 0, 100) . "...\n";
        echo "Is Cacheable: " . ($this->status->isCacheable() ? 'Yes' : 'No') . "\n";
        echo "May Have Body: " . ($this->status->mayHaveBody() ? 'Yes' : 'No') . "\n";
        echo "========================\n\n";
    }

    /**
     * NEU: JSON Response Helper
     */
    public function json(array|object $data, int $flags = JSON_THROW_ON_ERROR): self
    {
        try {
            $json = json_encode($data, $flags);
            return $this->withContentType('application/json')
                ->withBody($json);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to encode JSON: ' . $e->getMessage());
        }
    }

    public function withBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    // ===================================================================
    // Debug Methods - ERWEITERT
    // ===================================================================

    public function withContentType(string $contentType): self
    {
        return $this->withHeader('Content-Type', $contentType);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * NEU: Download Response Helper
     */
    public function download(string $content, string $filename, string $contentType = 'application/octet-stream'): self
    {
        return $this->withHeaders([
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string)strlen($content),
        ])->withBody($content);
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = [...$this->headers, ...$headers]; // MODERNISIERT
        return $this;
    }
}
