<?php
declare(strict_types=1);

namespace Framework\Http;

use Framework\Templating\Utils\JsonUtility;
use InvalidArgumentException;
use JsonException;

/**
 * HTTP Response - Modernisiert mit JsonUtility Integration
 */
class Response
{
    private bool $sent = false;

    public function __construct(
        private HttpStatus $status = HttpStatus::OK,
        private array $headers = [],
        private string $body = ''
    ) {
    }

    /**
     * MODERNISIERT: JSON Response mit JsonUtility
     */
    public function json(array|object $data, int $flags = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR): self
    {
        try {
            $json = JsonUtility::encode($data, $flags);
            return $this->withContentType('application/json; charset=utf-8')
                ->withBody($json);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to encode JSON: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * HINZUGEFÜGT: Pretty JSON für Debug/Development
     */
    public function jsonPretty(array|object $data): self
    {
        try {
            $json = JsonUtility::prettyEncode($data);
            return $this->withContentType('application/json; charset=utf-8')
                ->withBody($json);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to encode pretty JSON: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * HINZUGEFÜGT: Minimaler JSON (kompakt)
     */
    public function jsonMinimal(array|object $data): self
    {
        try {
            $json = JsonUtility::encodeMinimal($data);
            return $this->withContentType('application/json; charset=utf-8')
                ->withBody($json);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to encode minimal JSON: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * HINZUGEFÜGT: API-Standard JSON-Response
     */
    public function apiResponse(mixed $data = null, string $message = '', array $meta = []): self
    {
        $response = [
            'success' => $this->status->isSuccessful(),
            'status' => $this->status->value,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $this->json($response);
    }

    /**
     * HINZUGEFÜGT: Error JSON-Response
     */
    public function jsonError(string $message, array $errors = [], mixed $code = null): self
    {
        $response = [
            'success' => false,
            'error' => $message,
            'status' => $this->status->value
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        if ($code !== null) {
            $response['code'] = $code;
        }

        return $this->json($response);
    }

    /**
     * HINZUGEFÜGT: Validierungsfehler JSON-Response
     */
    public function jsonValidationError(array $errors, string $message = 'Validation failed'): self
    {
        return $this->withStatus(HttpStatus::UNPROCESSABLE_ENTITY)
            ->jsonError($message, $errors, 'VALIDATION_ERROR');
    }

    /**
     * MODERNISIERT: JSON Factory-Methode
     */
    public static function jsonResponse(array|object $data, HttpStatus $status = HttpStatus::OK): self
    {
        return new self($status)->json($data);
    }

    /**
     * HINZUGEFÜGT: API Success Response
     */
    public static function apiSuccess(mixed $data = null, string $message = 'Success', HttpStatus $status = HttpStatus::OK): self
    {
        return new self($status)->apiResponse($data, $message);
    }

    /**
     * HINZUGEFÜGT: API Error Response
     */
    public static function apiError(string $message, HttpStatus $status = HttpStatus::BAD_REQUEST, array $errors = []): self
    {
        return new self($status)->jsonError($message, $errors);
    }

    /**
     * HINZUGEFÜGT: Validation Error Response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): self
    {
        return new self(HttpStatus::UNPROCESSABLE_ENTITY)->jsonValidationError($errors, $message);
    }

    /**
     * HINZUGEFÜGT: Not Found JSON Response
     */
    public static function notFoundJson(string $message = 'Resource not found'): self
    {
        return new self(HttpStatus::NOT_FOUND)->jsonError($message, [], 'NOT_FOUND');
    }

    /**
     * HINZUGEFÜGT: Unauthorized JSON Response
     */
    public static function unauthorizedJson(string $message = 'Unauthorized'): self
    {
        return new self(HttpStatus::UNAUTHORIZED)->jsonError($message, [], 'UNAUTHORIZED');
    }

    // ===================================================================
    // BESTEHENDE FACTORY METHODS (unverändert)
    // ===================================================================

    public static function ok(string $body = 'OK'): self
    {
        return new self(HttpStatus::OK, [], $body);
    }

    public static function created(string $body = 'Created'): self
    {
        return new self(HttpStatus::CREATED, [], $body);
    }

    public static function noContent(): self
    {
        return new self(HttpStatus::NO_CONTENT);
    }

    public static function redirect(string $url, HttpStatus $status = HttpStatus::FOUND): self
    {
        return new self($status, ['Location' => $url]);
    }

    public static function badRequest(string $body = 'Bad Request'): self
    {
        return new self(HttpStatus::BAD_REQUEST, [], $body);
    }

    public static function unauthorized(string $body = 'Unauthorized'): self
    {
        return new self(HttpStatus::UNAUTHORIZED, [], $body);
    }

    public static function forbidden(string $body = 'Forbidden'): self
    {
        return new self(HttpStatus::FORBIDDEN, [], $body);
    }

    public static function notFound(string $body = 'Not Found'): self
    {
        return new self(HttpStatus::NOT_FOUND, [], $body);
    }

    public static function methodNotAllowed(string $body = 'Method Not Allowed'): self
    {
        return new self(HttpStatus::METHOD_NOT_ALLOWED, [], $body);
    }

    public static function internalServerError(string $body = 'Internal Server Error'): self
    {
        return new self(HttpStatus::INTERNAL_SERVER_ERROR, [], $body);
    }
    public function withStatus(HttpStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = [...$this->headers, ...$headers];
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

    /**
     * HINZUGEFÜGT: CORS Headers setzen
     */
    public function withCors(array $options = []): self
    {
        $defaults = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Max-Age' => '86400'
        ];

        $corsHeaders = [...$defaults, ...$options];
        return $this->withHeaders($corsHeaders);
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

    public function getBody(): string
    {
        return $this->body;
    }

    public function isSent(): bool
    {
        return $this->sent;
    }
    public function send(): void
    {
        if ($this->sent) {
            throw new \RuntimeException('Response has already been sent');
        }

        // Status header senden
        http_response_code($this->status->value);

        // Headers senden
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Body senden
        echo $this->body;

        $this->sent = true;
    }
}