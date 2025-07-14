<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Core\Application;
use InvalidArgumentException;
use JsonException;

/**
 * HTTP Response - Mutable Response Object
 *
 * Note: Static factory methods are deprecated. Use ResponseFactory service instead.
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

    /**
     * Factory Methods für häufige Response-Typen
     *
     * @deprecated Use ResponseFactory service instead
     */
    public static function ok(string $body = '', array $headers = []): self
    {
        return new self(HttpStatus::OK, $headers, $body);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function created(string $body = '', array $headers = []): self
    {
        return new self(HttpStatus::CREATED, $headers, $body);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function noContent(array $headers = []): self
    {
        return new self(HttpStatus::NO_CONTENT, $headers);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function redirect(string $url, HttpStatus $status = HttpStatus::FOUND): self
    {
        if (!$status->isRedirect()) {
            throw new InvalidArgumentException('Status code must be a redirect status');
        }

        return new self($status, ['Location' => $url]);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public function isRedirect(): bool
    {
        return $this->status->isRedirection();
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function temporaryRedirect(string $url): self
    {
        return self::redirect($url, HttpStatus::TEMPORARY_REDIRECT);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function badRequest(string $body = 'Bad Request', array $headers = []): self
    {
        return new self(HttpStatus::BAD_REQUEST, $headers, $body);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function unauthorized(string $body = 'Unauthorized', array $headers = []): self
    {
        return new self(HttpStatus::UNAUTHORIZED, $headers, $body);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function forbidden(string $body = 'Forbidden', array $headers = []): self
    {
        return new self(HttpStatus::FORBIDDEN, $headers, $body);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function notFound(string $body = 'Not Found', array $headers = []): self
    {
        return new self(HttpStatus::NOT_FOUND, $headers, $body);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function methodNotAllowed(string $body = 'Method Not Allowed', array $headers = []): self
    {
        return new self(HttpStatus::METHOD_NOT_ALLOWED, $headers, $body);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function unprocessableEntity(string $body = 'Unprocessable Entity', array $headers = []): self
    {
        return new self(HttpStatus::UNPROCESSABLE_ENTITY, $headers, $body);
    }

    /**
     * @deprecated Use ResponseFactory service instead
     */
    public static function serverError(string $body = 'Internal Server Error', array $headers = []): self
    {
        return new self(HttpStatus::INTERNAL_SERVER_ERROR, $headers, $body);
    }

    /**
     * Template Response mit JSON-Fallback
     *
     * @deprecated Use ResponseFactory service instead
     */
    public static function viewOrJson(
        string     $template,
        array      $data = [],
        bool       $wantsJson = false,
        HttpStatus $status = HttpStatus::OK
    ): self
    {
        if ($wantsJson) {
            return self::json($data, $status);
        }

        return self::view($template, $data, $status);
    }

    /**
     * JSON Response Factory
     *
     * @deprecated Use ResponseFactory service instead
     */
    public static function json(
        array      $data,
        HttpStatus $status = HttpStatus::OK,
        array      $headers = [],
        int        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ): self
    {
        $headers['Content-Type'] = 'application/json; charset=UTF-8';

        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR | $options);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON data: ' . $e->getMessage());
        }

        return new self($status, $headers, $body);
    }

    /**
     * Template Response Factory - TRANSITION METHOD
     *
     * @deprecated Use ResponseFactory service instead
     */
    public static function view(
        string     $template,
        array      $data = [],
        HttpStatus $status = HttpStatus::OK,
        array      $headers = []
    ): self
    {
        // Transition method - use Application instance temporarily
        try {
            $app = Application::getInstance();
            $responseFactory = $app->getResponseFactory();
            return $responseFactory->view($template, $data, $status, $headers);
        } catch (\Throwable $e) {
            // Fallback for cases where Application is not available
            throw new \RuntimeException(
                'Response::view() is deprecated. Use ResponseFactory service instead. ' .
                'Original error: ' . $e->getMessage()
            );
        }
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
        return $this->status->isSuccess();
    }

    // Status Checks

    public function isError(): bool
    {
        return $this->status->isError();
    }

    public function isJson(): bool
    {
        return str_contains($this->getContentType(), 'application/json');
    }

    public function getContentType(): string
    {
        return $this->getHeader('Content-Type') ?? 'text/html; charset=UTF-8';
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
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

        // FastCGI finish request if available
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Sendet nur Headers (für HEAD-Requests)
     */
    public function sendHeaders(): void
    {
        if ($this->sent) {
            throw new InvalidArgumentException('Response has already been sent');
        }

        if (headers_sent($file, $line)) {
            throw new InvalidArgumentException("Headers already sent in {$file} on line {$line}");
        }

        http_response_code($this->status->value);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        $this->sent = true;
    }

    /**
     * Erstellt HTTP-Response String (für Testing)
     */
    public function toHttpString(): string
    {
        $lines = [];
        $lines[] = "HTTP/1.1 {$this->status->value} {$this->status->getText()}";

        foreach ($this->headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        $lines[] = '';  // Empty line zwischen Headers und Body
        $lines[] = $this->body;

        return implode("\r\n", $lines);
    }

    /**
     * Debug-Information
     */
    public function debug(): array
    {
        return [
            'status' => [
                'code' => $this->status->value,
                'text' => $this->status->getText(),
                'category' => match (true) {
                    $this->status->isInformational() => 'informational',
                    $this->status->isSuccess() => 'success',
                    $this->status->isRedirection() => 'redirection',
                    $this->status->isClientError() => 'client_error',
                    $this->status->isServerError() => 'server_error',
                    default => 'unknown',
                },
            ],
            'headers' => $this->headers,
            'body_length' => strlen($this->body),
            'content_type' => $this->getContentType(),
            'sent' => $this->sent,
        ];
    }

    public function isClientError(): bool
    {
        return $this->status->isClientError();
    }

    public function isServerError(): bool
    {
        return $this->status->isServerError();
    }

    /**
     * Konvertiert Response zu String (gibt Body zurück)
     */
    public function __toString(): string
    {
        return $this->body;
    }
}