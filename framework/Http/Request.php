<?php
declare(strict_types=1);

namespace Framework\Http;

use Framework\Templating\Utils\JsonUtility;
use JsonException;

/**
 * HTTP Request - Modernisiert mit JsonUtility Integration
 */
readonly class Request
{
    /**
     * MODERNISIERT: Typed Class Constants (PHP 8.3+)
     */
    private const string DEFAULT_PROTOCOL = 'HTTP/1.1';
    private const array TRUSTED_HEADERS = [
        'x-forwarded-for',
        'x-forwarded-proto',
        'x-forwarded-port',
        'x-real-ip',
    ];

    public function __construct(
        private HttpMethod $method,
        private string     $uri,
        private array      $headers = [],
        private array      $query = [],
        private array      $post = [],
        private array      $files = [],
        private array      $cookies = [],
        private array      $server = [],
        private string     $body = '',
        private string     $protocol = self::DEFAULT_PROTOCOL,
        private array      $pathParameters = [],
    ) {
    }

    /**
     * Erstellt Request aus globalen PHP-Variablen
     */
    public static function fromGlobals(): self
    {
        $method = HttpMethod::from($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = self::parseHeaders();
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? self::DEFAULT_PROTOCOL;

        return new self(
            method: $method,
            uri: $uri,
            headers: $headers,
            query: $_GET,
            post: $_POST,
            files: $_FILES,
            cookies: $_COOKIE,
            server: $_SERVER,
            body: file_get_contents('php://input') ?: '',
            protocol: $protocol,
            pathParameters: [],
        );
    }

    /**
     * MODERNISIERT: Bessere Header-Parsing mit PHP 8.4 Features
     */
    private static function parseHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] = $value;
            }
        }

        // Spezielle Headers hinzufügen
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        // Basic Auth Support
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $credentials = $_SERVER['PHP_AUTH_USER'] . ':' . ($_SERVER['PHP_AUTH_PW'] ?? '');
            $headers['authorization'] = 'Basic ' . base64_encode($credentials);
        }

        return $headers;
    }

    /**
     * MODERNISIERT: JSON Body Parsing mit JsonUtility
     */
    public function json(bool $associative = true): array|object
    {
        // Schnelle Validierung mit JsonUtility
        if (!JsonUtility::isValid($this->body)) {
            throw new JsonException('Invalid JSON in request body');
        }

        try {
            return JsonUtility::decode($this->body, $associative);
        } catch (JsonException $e) {
            throw new JsonException('Failed to parse JSON: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * HINZUGEFÜGT: Sichere JSON-Decodierung mit Fallback
     */
    public function jsonSafe(mixed $fallback = [], bool $associative = true): mixed
    {
        try {
            return $this->json($associative);
        } catch (JsonException) {
            return $fallback;
        }
    }

    /**
     * VERBESSERT: JSON-Validierung für Request Body
     */
    public function hasValidJson(): bool
    {
        return !empty($this->body) && JsonUtility::isValid($this->body);
    }

    /**
     * HINZUGEFÜGT: JSON-Input mit Dot-Notation
     */
    public function jsonInput(string $key, mixed $default = null): mixed
    {
        if (!$this->hasValidJson()) {
            return $default;
        }

        try {
            $data = $this->json(true);
            return $this->getNestedValue($data, $key, $default);
        } catch (JsonException) {
            return $default;
        }
    }

    /**
     * HINZUGEFÜGT: Nested Array-Zugriff mit Dot-Notation
     */
    private function getNestedValue(array $data, string $key, mixed $default = null): mixed
    {
        if (!str_contains($key, '.')) {
            return $data[$key] ?? $default;
        }

        $keys = explode('.', $key);
        $current = $data;

        foreach ($keys as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * ERWEITERT: Content-Type-Prüfung
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('content-type');

        if (!$contentType) {
            return false;
        }

        // Robustere Content-Type-Prüfung
        return str_contains(strtolower($contentType), 'application/json');
    }

    /**
     * ERWEITERT: Erweiterte JSON-Request-Erkennung
     */
    public function expectsJson(): bool
    {
        // 1. Accept Header prüfen (primäre Methode)
        $accept = $this->getHeader('accept');
        if ($accept && str_contains(strtolower($accept), 'application/json')) {
            return true;
        }

        // 2. Content-Type ist JSON (API-Request)
        if ($this->isJson()) {
            return true;
        }

        // 3. AJAX-Request über XMLHttpRequest
        $requestedWith = $this->getHeader('x-requested-with');
        if ($requestedWith && strtolower($requestedWith) === 'xmlhttprequest') {
            return true;
        }

        // 4. API-Endpoints (heuristische Prüfung)
        $path = $this->getPathInfo();
        if (str_contains($path, '/api/') || str_ends_with($path, '.json')) {
            return true;
        }

        return false;
    }

    /**
     * HINZUGEFÜGT: JSON-Response-Format-Validierung
     */
    public function validateJsonStructure(array $requiredFields): array
    {
        $errors = [];

        if (!$this->hasValidJson()) {
            return ['json' => 'Request body is not valid JSON'];
        }

        try {
            $data = $this->json(true);

            foreach ($requiredFields as $field => $type) {
                if (!array_key_exists($field, $data)) {
                    $errors[$field] = "Required field '{$field}' is missing";
                    continue;
                }

                $value = $data[$field];
                $actualType = gettype($value);

                if ($type !== 'mixed' && $actualType !== $type) {
                    $errors[$field] = "Field '{$field}' must be of type '{$type}', got '{$actualType}'";
                }
            }

        } catch (JsonException $e) {
            $errors['json'] = 'Failed to parse JSON: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * HINZUGEFÜGT: JSON-Debug-Informationen
     */
    public function getJsonDebugInfo(): array
    {
        if (empty($this->body)) {
            return ['has_body' => false, 'valid_json' => false];
        }

        $validation = JsonUtility::validateWithDetails($this->body);

        return [
            'has_body' => true,
            'body_length' => strlen($this->body),
            'valid_json' => $validation['valid'],
            'error' => $validation['error'],
            'looks_like_json' => JsonUtility::looksLikeJson($this->body),
            'content_type' => $this->getHeader('content-type'),
            'body_preview' => substr($this->body, 0, 100) . (strlen($this->body) > 100 ? '...' : '')
        ];
    }
    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?: '/';
    }

    public function getPathInfo(): string
    {
        return $this->getPath();
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getPost(): array
    {
        return $this->post;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function getServer(): array
    {
        return $this->server;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getPathParameters(): array
    {
        return $this->pathParameters;
    }

    public function getPathParameter(string $name, mixed $default = null): mixed
    {
        return $this->pathParameters[$name] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $this->post[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->query[$key]) || isset($this->post[$key]);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function all(): array
    {
        return [...$this->query, ...$this->post];
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    public function withPathParameters(array $parameters): self
    {
        return new self(
            method: $this->method,
            uri: $this->uri,
            headers: $this->headers,
            query: $this->query,
            post: $this->post,
            files: $this->files,
            cookies: $this->cookies,
            server: $this->server,
            body: $this->body,
            protocol: $this->protocol,
            pathParameters: [...$this->pathParameters, ...$parameters],
        );
    }

    public function withBody(string $body): self
    {
        return new self(
            method: $this->method,
            uri: $this->uri,
            headers: $this->headers,
            query: $this->query,
            post: $this->post,
            files: $this->files,
            cookies: $this->cookies,
            server: $this->server,
            body: $body,
            protocol: $this->protocol,
            pathParameters: $this->pathParameters,
        );
    }

    public function withQuery(array $query): self
    {
        return new self(
            method: $this->method,
            uri: $this->uri,
            headers: $this->headers,
            query: [...$this->query, ...$query],
            post: $this->post,
            files: $this->files,
            cookies: $this->cookies,
            server: $this->server,
            body: $this->body,
            protocol: $this->protocol,
            pathParameters: $this->pathParameters,
        );
    }

    public function ip(): string
    {
        // Prüfe vertrauenswürdige Proxy-Headers
        foreach (self::TRUSTED_HEADERS as $header) {
            if ($ip = $this->getHeader($header)) {
                return explode(',', $ip)[0];
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function getUserAgent(): ?string
    {
        return $this->getHeader('user-agent');
    }

    public function getReferer(): ?string
    {
        return $this->getHeader('referer');
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? 'off') !== 'off'
            || ($this->server['SERVER_PORT'] ?? 80) == 443
            || strtolower($this->getHeader('x-forwarded-proto') ?? '') === 'https';
    }

    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    public function getHost(): string
    {
        return $this->getHeader('host') ?? $this->server['SERVER_NAME'] ?? 'localhost';
    }

    public function getFullUrl(): string
    {
        return $this->getScheme() . '://' . $this->getHost() . $this->uri;
    }
}