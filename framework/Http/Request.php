<?php

declare(strict_types=1);

namespace Framework\Http;

use JsonException;

/**
 * HTTP Request - Immutable Request Object mit PHP 8.4 Features
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
    )
    {
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

    // ===================================================================
    // Immutable With Methods - PHP 8.4 optimiert
    // ===================================================================

    public function withUri(string $uri): self
    {
        return new self(
            method: $this->method,
            uri: $uri,
            headers: $this->headers,
            query: $this->query,
            post: $this->post,
            files: $this->files,
            cookies: $this->cookies,
            server: $this->server,
            body: $this->body,
            protocol: $this->protocol,
            pathParameters: $this->pathParameters,
        );
    }

    public function withMethod(HttpMethod $method): self
    {
        return new self(
            method: $method,
            uri: $this->uri,
            headers: $this->headers,
            query: $this->query,
            post: $this->post,
            files: $this->files,
            cookies: $this->cookies,
            server: $this->server,
            body: $this->body,
            protocol: $this->protocol,
            pathParameters: $this->pathParameters,
        );
    }

    public function withHeaders(array $headers): self
    {
        return new self(
            method: $this->method,
            uri: $this->uri,
            headers: [...$this->headers, ...$headers], // MODERNISIERT: Spread Operator
            query: $this->query,
            post: $this->post,
            files: $this->files,
            cookies: $this->cookies,
            server: $this->server,
            body: $this->body,
            protocol: $this->protocol,
            pathParameters: $this->pathParameters,
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
            pathParameters: [...$this->pathParameters, ...$parameters], // MODERNISIERT
        );
    }

    public function withQuery(array $query): self
    {
        return new self(
            method: $this->method,
            uri: $this->uri,
            headers: $this->headers,
            query: [...$this->query, ...$query], // MODERNISIERT
            post: $this->post,
            files: $this->files,
            cookies: $this->cookies,
            server: $this->server,
            body: $this->body,
            protocol: $this->protocol,
            pathParameters: $this->pathParameters,
        );
    }

    // ===================================================================
    // Getter Methods - ERWEITERT
    // ===================================================================

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
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

    public function getPathParameters(): array
    {
        return $this->pathParameters;
    }

    public function getPathParameter(string $name, mixed $default = null): mixed
    {
        return $this->pathParameters[$name] ?? $default;
    }

    /**
     * NEU: Holt Input-Wert aus Query oder Post (ähnlich Laravel)
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $this->post[$key] ?? $default;
    }

    // ===================================================================
    // Enhanced Methods - NEU für PHP 8.4
    // ===================================================================

    /**
     * NEU: Prüft ob Input existiert
     */
    public function has(string $key): bool
    {
        return isset($this->query[$key]) || isset($this->post[$key]);
    }

    /**
     * NEU: Holt nur bestimmte Input-Felder
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    /**
     * NEU: Holt alle Input-Werte
     */
    public function all(): array
    {
        return [...$this->query, ...$this->post]; // MODERNISIERT: Spread
    }

    /**
     * NEU: Schließt bestimmte Input-Felder aus
     */
    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * NEU: JSON Body Parsing mit json_validate (PHP 8.3+)
     */
    public function json(): array
    {
        if (!json_validate($this->body)) { // MODERNISIERT: json_validate
            throw new JsonException('Invalid JSON in request body');
        }

        try {
            return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonException('Failed to parse JSON: ' . $e->getMessage());
        }
    }

    /**
     * NEU: Prüft ob Request JSON ist
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('content-type');
        return $contentType && str_contains($contentType, 'application/json');
    }

    /**
     * VERBESSERT: Header-Zugriff mit Case-Insensitive
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * MODERNISIERT: File Handling mit besserer Typisierung
     */
    public function file(string $name): ?array
    {
        return $this->files[$name] ?? null;
    }

    public function hasFile(string $name): bool
    {
        return isset($this->files[$name]) &&
            is_array($this->files[$name]) &&
            ($this->files[$name]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    /**
     * NEU: Aliases für bessere API
     */
    public function files(): array
    {
        return $this->getFiles();
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getUserAgent(): ?string
    {
        return $this->getHeader('user-agent');
    }

    /**
     * NEU: IP-Adresse mit Proxy-Support
     */
    public function getClientIp(): string
    {
        // Prüfe Proxy-Headers (nur wenn vertrauenswürdig)
        foreach (self::TRUSTED_HEADERS as $header) {
            if ($ip = $this->getHeader($header)) {
                // Ersten IP aus komma-separierter Liste nehmen
                $ip = explode(',', $ip)[0];
                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * NEU: HTTPS Detection
     */
    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? 'off') !== 'off') {
            return true;
        }

        if (($this->server['SERVER_PORT'] ?? 80) == 443) {
            return true;
        }

        $proto = $this->getHeader('x-forwarded-proto');
        return $proto === 'https';
    }

    /**
     * NEU: Ajax Detection
     */
    public function isAjax(): bool
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?? '/';
    }
}