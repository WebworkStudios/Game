<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * Request - PHP 8.4 Enhanced HTTP Request
 *
 * OPTIMIERUNGEN:
 * ✅ Typed constants für bessere Performance
 * ✅ Match expressions für Input-Handling
 * ✅ Readonly properties wo möglich
 * ✅ Enhanced security features
 */
final class Request
{
    // PHP 8.4: Typed Constants
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    private const array JSON_CONTENT_TYPES = [
        'application/json',
        'application/vnd.api+json',
        'text/json'
    ];
    private const int MAX_INPUT_SIZE = 1048576; // 1MB

    private readonly array $query;
    private readonly array $post;
    private readonly array $cookies;
    private readonly array $server;
    private readonly array $headers;
    private readonly string $body;
    private array $pathParameters = [];
    private ?array $json = null;
    private ?array $files = null;

    public function __construct(
        private readonly HttpMethod $method,
        private readonly string $uri,
        array $query = [],
        array $post = [],
        array $cookies = [],
        array $server = [],
        array $headers = [],
        string $body = '',
        private readonly string $protocol = 'HTTP/1.1'
    ) {
        $this->query = $this->sanitizeInput($query);
        $this->post = $this->sanitizeInput($post);
        $this->cookies = $cookies;
        $this->server = $server;
        $this->headers = $this->normalizeHeaders($headers);
        $this->body = $this->validateBodySize($body);
    }

    /**
     * Create Request from Global Variables - PHP 8.4 Enhanced
     */
    public static function fromGlobals(): self
    {
        $method = HttpMethod::fromString($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = self::getUriFromGlobals();
        $headers = self::getHeadersFromGlobals();
        $body = self::getBodyFromGlobals();

        return new self(
            method: $method,
            uri: $uri,
            query: $_GET ?? [],
            post: $_POST ?? [],
            cookies: $_COOKIE ?? [],
            server: $_SERVER ?? [],
            headers: $headers,
            body: $body,
            protocol: $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1'
        );
    }

    /**
     * Get URI from Globals - Enhanced
     */
    private static function getUriFromGlobals(): string
    {
        // Check for custom headers first (proxy support)
        $uri = $_SERVER['HTTP_X_ORIGINAL_URL']
            ?? $_SERVER['HTTP_X_REWRITE_URL']
            ?? $_SERVER['REQUEST_URI']
            ?? '/';

        // Remove query string if present
        return strtok($uri, '?') ?: '/';
    }

    /**
     * Get Headers from Globals
     */
    private static function getHeadersFromGlobals(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = substr($key, 5);
                $header = str_replace('_', '-', $header);
                $header = strtolower($header);
                $headers[$header] = $value;
            }
        }

        // Add content-type if not in HTTP_ headers
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        // Add content-length if available
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * Get Body from Globals
     */
    private static function getBodyFromGlobals(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    /**
     * Sanitize Input - Security Enhancement
     */
    private function sanitizeInput(array $input): array
    {
        return array_map(function ($value) {
            return match (true) {
                is_string($value) => trim($value),
                is_array($value) => $this->sanitizeInput($value),
                default => $value
            };
        }, $input);
    }

    /**
     * Normalize Headers - Case Insensitive
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }
        return $normalized;
    }

    /**
     * Validate Body Size - Security Check
     */
    private function validateBodySize(string $body): string
    {
        if (strlen($body) > self::MAX_INPUT_SIZE) {
            throw new \InvalidArgumentException('Request body too large');
        }
        return $body;
    }

    // Basic Getters
    public function getMethod(): HttpMethod { return $this->method; }
    public function getUri(): string { return $this->uri; }
    public function getQuery(): array { return $this->query; }
    public function getPost(): array { return $this->post; }
    public function getCookies(): array { return $this->cookies; }
    public function getServer(): array { return $this->server; }
    public function getHeaders(): array { return $this->headers; }
    public function getBody(): string { return $this->body; }
    public function getProtocol(): string { return $this->protocol; }

    /**
     * Get Path from URI
     */
    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?: '/';
    }

    /**
     * Get Header - Case Insensitive
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Check Header Existence
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    // Path Parameters
    public function getPathParameters(): array { return $this->pathParameters; }

    public function getPathParameter(string $name, mixed $default = null): mixed
    {
        return $this->pathParameters[$name] ?? $default;
    }

    public function withPathParameters(array $parameters): self
    {
        $clone = clone $this;
        $clone->pathParameters = $parameters;
        return $clone;
    }

    /**
     * Get Input Value - PHP 8.4 Enhanced with Match
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return match (true) {
            isset($this->query[$key]) => $this->query[$key],
            isset($this->post[$key]) => $this->post[$key],
            $this->isJson() && isset($this->json()[$key]) => $this->json()[$key],
            default => $default
        };
    }

    /**
     * Get All Input Data
     */
    public function all(): array
    {
        return match (true) {
            $this->isJson() => $this->json() ?? [],
            default => [...$this->query, ...$this->post]
        };
    }

    /**
     * Get Only Specified Keys
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    /**
     * Get All Except Specified Keys
     */
    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    /**
     * Check if Request is JSON - PHP 8.4 Enhanced
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('content-type') ?? '';

        return match (true) {
            empty($contentType) => false,
            default => (bool) array_filter(
                self::JSON_CONTENT_TYPES,
                fn($type) => str_contains($contentType, $type)
            )
        };
    }

    /**
     * Get JSON Data - Cached Parsing
     */
    public function json(): ?array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        if (!$this->isJson() || empty($this->body)) {
            return $this->json = null;
        }

        try {
            $this->json = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
            return $this->json;
        } catch (\JsonException) {
            return $this->json = null;
        }
    }

    /**
     * Check if Request Expects JSON Response
     */
    public function expectsJson(): bool
    {
        $accept = $this->getHeader('accept') ?? '';
        return str_contains($accept, 'application/json')
            || $this->hasHeader('x-requested-with');
    }

    /**
     * Check if Request is AJAX
     */
    public function isAjax(): bool
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Check if Method is Safe (Read-only)
     */
    public function isSafe(): bool
    {
        return $this->method->isSafe();
    }

    /**
     * Get Referer URL
     */
    public function getReferer(): ?string
    {
        return $this->getHeader('referer');
    }

    /**
     * Get User Agent
     */
    public function getUserAgent(): ?string
    {
        return $this->getHeader('user-agent');
    }

    /**
     * Get Client IP - Enhanced with Proxy Support
     */
    public function getClientIp(): string
    {
        // Check for IP from various proxy headers
        $headers = [
            'x-forwarded-for',
            'x-real-ip',
            'x-client-ip',
            'cf-connecting-ip'
        ];

        foreach ($headers as $header) {
            $ip = $this->getHeader($header);
            if ($ip && $this->isValidIp($ip)) {
                return explode(',', $ip)[0];
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Validate IP Address
     */
    private function isValidIp(string $ip): bool
    {
        return filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Check if Request is Secure (HTTPS)
     */
    public function isSecure(): bool
    {
        return match (true) {
            isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off' => true,
            isset($this->server['SERVER_PORT']) && $this->server['SERVER_PORT'] == 443 => true,
            $this->getHeader('x-forwarded-proto') === 'https' => true,
            $this->getHeader('x-forwarded-ssl') === 'on' => true,
            default => false
        };
    }

    /**
     * Get Full URL
     */
    public function getFullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->getHeader('host') ?? $this->server['SERVER_NAME'] ?? 'localhost';

        return "{$scheme}://{$host}{$this->uri}";
    }

    /**
     * Debug Information
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method->value,
            'uri' => $this->uri,
            'path' => $this->getPath(),
            'query' => $this->query,
            'post' => $this->post,
            'headers' => $this->headers,
            'is_json' => $this->isJson(),
            'is_ajax' => $this->isAjax(),
            'is_secure' => $this->isSecure(),
            'client_ip' => $this->getClientIp(),
            'body_size' => strlen($this->body)
        ];
    }
}
