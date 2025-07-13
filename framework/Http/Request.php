<?php

declare(strict_types=1);

namespace Framework\Http;

use JsonException;

/**
 * HTTP Request - Immutable Request Object
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
        );
    }

    // Getters

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
        );
    }

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?? '/';
    }

    public function getUserAgent(): ?string
    {
        return $this->getHeader('user-agent');
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getAcceptLanguage(): ?string
    {
        return $this->getHeader('accept-language');
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getPost(): array
    {
        return $this->post;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function getServer(): array
    {
        return $this->server;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getFullUrl(): string
    {
        $scheme = $this->getScheme();
        $host = $this->getHost();
        $port = $this->getPort();
        $uri = $this->getUri();

        // Standard-Ports weglassen
        $portString = (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))
            ? ''
            : ":{$port}";

        return "{$scheme}://{$host}{$portString}{$uri}";
    }

    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? 'off') !== 'off'
            || ($this->server['SERVER_PORT'] ?? 80) == 443
            || $this->getHeader('x-forwarded-proto') === 'https';
    }

    public function getHost(): string
    {
        return $this->getHeader('host') ?? $this->server['HTTP_HOST'] ?? 'localhost';
    }

    // Convenience Methods

    public function getPort(): int
    {
        if ($port = $this->server['SERVER_PORT'] ?? null) {
            return (int)$port;
        }

        return $this->isSecure() ? 443 : 80;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function isGet(): bool
    {
        return $this->method === HttpMethod::GET;
    }

    public function isPost(): bool
    {
        return $this->method === HttpMethod::POST;
    }

    public function isPut(): bool
    {
        return $this->method === HttpMethod::PUT;
    }

    public function isDelete(): bool
    {
        return $this->method === HttpMethod::DELETE;
    }

    public function isPatch(): bool
    {
        return $this->method === HttpMethod::PATCH;
    }

    public function isHead(): bool
    {
        return $this->method === HttpMethod::HEAD;
    }

    public function isOptions(): bool
    {
        return $this->method === HttpMethod::OPTIONS;
    }

    public function isXmlHttpRequest(): bool
    {
        return $this->isAjax();
    }

    public function isAjax(): bool
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    public function wantsJson(): bool
    {
        return $this->expectsJson() || $this->isAjax();
    }

    public function expectsJson(): bool
    {
        $accept = $this->getHeader('accept') ?? '';

        // Nur echte JSON-Requests - nicht Browser mit */*
        return str_contains($accept, 'application/json') &&
            !str_contains($accept, 'text/html');
    }

    /**
     * Holt nur bestimmte Keys aus Input
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    /**
     * Holt alle Input-Daten als Array
     */
    public function all(): array
    {
        // JSON hat Priorität bei JSON Content-Type
        if ($this->isJson()) {
            return array_merge($this->query, $this->json());
        }

        return array_merge($this->query, $this->post, $this->json());
    }

    public function isJson(): bool
    {
        $contentType = $this->getHeader('content-type') ?? '';
        return str_contains($contentType, 'application/json');
    }

    /**
     * Parst JSON-Body
     */
    public function json(): array
    {
        if (!$this->isJson() || empty($this->body)) {
            return [];
        }

        try {
            $decoded = json_decode($this->body, true, flags: JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Holt alle Keys außer den angegebenen
     */
    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    /**
     * Prüft ob Key in Input existiert
     */
    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    /**
     * Holt Input-Wert (POST > JSON > Query Priority)
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->json()[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Prüft ob Key in Input existiert und nicht leer ist
     */
    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '' && $value !== [];
    }

    /**
     * Holt User-Agent String
     */
    public function userAgent(): string
    {
        return $this->getHeader('user-agent') ?? '';
    }

    /**
     * Holt Client IP-Adresse
     */
    public function ip(): string
    {
        // Proxy-Headers prüfen
        $proxies = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_CLUSTER_CLIENT_IP',
        ];

        foreach ($proxies as $proxy) {
            if (!empty($this->server[$proxy])) {
                $ips = explode(',', $this->server[$proxy]);
                return trim($ips[0]);
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}