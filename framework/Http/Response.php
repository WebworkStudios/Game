<?php
declare(strict_types=1);

namespace Framework\Http;

use JsonException;

/**
 * Response - PHP 8.4 Enhanced HTTP Response
 */
final class Response
{
    private bool $sent = false;

    public function __construct(
        private HttpStatus $status = HttpStatus::OK,
        private array $headers = [],
        private string $body = ''
    ) {}

    /**
     * Factory Methods - PHP 8.4 Enhanced
     */
    public static function ok(string $body = 'OK'): self
    {
        return new self(HttpStatus::OK, [], $body);
    }

    /**
     * @throws JsonException
     */
    public static function json(array|object $data, HttpStatus $status = HttpStatus::OK): self
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
            $json
        );
    }

    public static function redirect(string $url, HttpStatus $status = HttpStatus::FOUND): self
    {
        return new self($status, ['Location' => $url]);
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return new self(HttpStatus::NOT_FOUND, [], $message);
    }

    public static function serverError(string $message = 'Internal Server Error'): self
    {
        return new self(HttpStatus::INTERNAL_SERVER_ERROR, [], $message);
    }

    /**
     * Fluent Interface Methods
     */
    public function withStatus(HttpStatus $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * Send Response to Client
     */
    public function send(): void
    {
        if ($this->sent) {
            throw new \RuntimeException('Response has already been sent');
        }

        // Send status
        http_response_code($this->status->value);

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Send body
        echo $this->body;

        $this->sent = true;
    }

    // Getters
    public function getStatus(): HttpStatus { return $this->status; }
    public function getHeaders(): array { return $this->headers; }
    public function getBody(): string { return $this->body; }
    public function isSent(): bool { return $this->sent; }
}
