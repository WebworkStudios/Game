<?php

declare(strict_types=1);

namespace Framework\Http;

use JsonException;

/**
 * HTTP Request - Immutable Request Object
 *
 * ERWEITERT: Mit Path Parameters Support für Router
 */
readonly class Request
{
    private const string DEFAULT_PROTOCOL = 'HTTP/1.1';

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
        private array      $pathParameters = [], // NEU: Route-Parameter
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
     * Parst HTTP-Headers aus $_SERVER
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

        // Spezielle Headers
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $headers['authorization'] = 'Basic ' . base64_encode(
                    $_SERVER['PHP_AUTH_USER'] . ':' . ($_SERVER['PHP_AUTH_PW'] ?? '')
                );
        }

        return $headers;
    }

    // ===================================================================
    // Immutable With Methods
    // ===================================================================

    /**
     * Erstellt neuen Request mit geänderter URI
     */
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

    /**
     * Erstellt neuen Request mit geänderter HTTP-Methode
     */
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

    /**
     * Erstellt neuen Request mit zusätzlichen Headers
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            method: $this->method,
            uri: $this->uri,
            headers: array_merge($this->headers, $headers),
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

    /**
     * Erstellt neuen Request mit neuem Body
     */
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

    /**
     * NEU: Erstellt neuen Request mit Path Parameters
     */
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
            pathParameters: array_merge($this->pathParameters, $parameters),
        );
    }

    /**
     * Erstellt neuen Request mit zusätzlichen Query-Parametern
     */
    public function withQuery(array $query): self
    {
        return new self(
            method: $this->method,
            uri: $this->uri,
            headers: $this->headers,
            query: array_merge($this->query, $query),
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
    // Getter Methods
    // ===================================================================

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
        return parse_url($this->uri, PHP_URL_PATH) ?? '/';
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getPost(): array
    {
        return $this->post;
    }

    /**
     * Holt Files-Daten
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Alias für getFiles() für bessere API-Konsistenz
     */
    public function files(): array
    {
        return $this->getFiles();
    }

    /**
     * Holt spezifische Datei
     */
    public function file(string $name): ?array
    {
        return $this->files[$name] ?? null;
    }

    /**
     * Prüft ob Datei hochgeladen wurde
     */
    public function hasFile(string $name): bool
    {
        return isset($this->files[$name]) &&
            is_array($this->files[$name]) &&
            $this->files[$name]['error'] === UPLOAD_ERR_OK;
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

    /**
     * NEU: Holt Path Parameters
     */
    public function getPathParameters(): array
    {
        return $this->pathParameters;
    }

    /**
     * NEU: Holt spezifischen Path Parameter
     */
    public function getPathParameter(string $name, mixed $default = null): mixed
    {
        return $this->pathParameters[$name] ?? $default;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getUserAgent(): ?string
    {
        return $this->getHeader('user-agent');
    }

    public function getContentType(): ?string
    {
        return $this->getHeader('content-type');
    }

    public function getContentLength(): ?int
    {
        $length = $this->getHeader('content-length');
        return $length !== null ? (int) $length : null;
    }

    // ===================================================================
    // Request Data Access Methods
    // ===================================================================

    /**
     * Holt Input-Wert aus Query oder Post
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $this->post[$key] ?? $this->pathParameters[$key] ?? $default;
    }

    /**
     * Holt alle Input-Daten
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->pathParameters);
    }

    /**
     * Holt nur spezifische Input-Felder
     */
    public function only(array $keys): array
    {
        $data = $this->all();
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * Holt alle Input-Daten außer spezifischen
     */
    public function except(array $keys): array
    {
        $data = $this->all();
        return array_diff_key($data, array_flip($keys));
    }

    /**
     * Prüft ob Input-Feld existiert
     */
    public function has(string $key): bool
    {
        return isset($this->query[$key]) || isset($this->post[$key]) || isset($this->pathParameters[$key]);
    }

    /**
     * Prüft ob Input-Feld existiert und nicht leer ist
     */
    public function filled(string $key): bool
    {
        return $this->has($key) && !empty($this->input($key));
    }

    // ===================================================================
    // Request Type Detection
    // ===================================================================

    /**
     * Prüft ob Request JSON erwartet
     */
    public function expectsJson(): bool
    {
        return $this->wantsJson() || $this->isJson();
    }

    /**
     * Prüft ob Request JSON will
     */
    public function wantsJson(): bool
    {
        $accept = $this->getHeader('accept');
        return $accept && str_contains($accept, 'application/json');
    }

    /**
     * Prüft ob Request JSON ist
     */
    public function isJson(): bool
    {
        $contentType = $this->getContentType();
        return $contentType && str_contains($contentType, 'application/json');
    }

    /**
     * Prüft ob Request AJAX ist
     */
    public function isAjax(): bool
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Prüft ob Request über HTTPS ist
     */
    public function isSecure(): bool
    {
        return $this->server['HTTPS'] === 'on' || $this->server['HTTP_X_FORWARDED_PROTO'] === 'https';
    }

    /**
     * Prüft ob Request ein POST ist
     */
    public function isPost(): bool
    {
        return $this->method === HttpMethod::POST;
    }

    /**
     * Prüft ob Request ein GET ist
     */
    public function isGet(): bool
    {
        return $this->method === HttpMethod::GET;
    }

    // ===================================================================
    // JSON/Body Parsing
    // ===================================================================

    /**
     * Parst JSON Body
     */
    public function json(): array
    {
        if (!$this->isJson()) {
            return [];
        }

        try {
            return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Holt JSON-Feld
     */
    public function jsonField(string $key, mixed $default = null): mixed
    {
        $json = $this->json();
        return $json[$key] ?? $default;
    }

    // ===================================================================
    // Debug Methods
    // ===================================================================

    /**
     * Debug-Ausgabe der Request-Daten
     */
    public function dump(): self
    {
        echo "\n=== HTTP REQUEST DEBUG ===\n";
        echo "Method: {$this->method->value}\n";
        echo "URI: {$this->uri}\n";
        echo "Path: {$this->getPath()}\n";
        echo "Query: " . json_encode($this->query) . "\n";
        echo "Post: " . json_encode($this->post) . "\n";
        echo "Path Parameters: " . json_encode($this->pathParameters) . "\n";
        echo "Headers: " . json_encode($this->headers) . "\n";
        echo "Body Length: " . strlen($this->body) . " bytes\n";
        echo "========================\n\n";

        return $this;
    }
}