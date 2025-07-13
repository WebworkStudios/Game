<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\Request;
use Random\RandomException;

/**
 * Enhanced CSRF Protection - Cross-Site Request Forgery Schutz mit Session Integration
 */
class Csrf
{
    private const string TOKEN_KEY = 'csrf_token';
    private const string TOKEN_FIELD_NAME = '_token';
    private const string TOKEN_HEADER_NAME = 'X-CSRF-TOKEN';
    private const int TOKEN_LENGTH = 32;
    private const int DEFAULT_LIFETIME = 7200; // 2 Stunden

    private array $config;

    public function __construct(
        private readonly Session $session,
        array                    $config = []
    )
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Default CSRF configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'token_lifetime' => self::DEFAULT_LIFETIME,
            'regenerate_on_login' => true,
            'require_for_safe_methods' => false,
            'strict_referer_check' => false,
            'auto_cleanup_expired' => true,
        ];
    }

    /**
     * Erstellt HTML-Input-Field für CSRF-Token
     */
    public function getTokenField(): string
    {
        $token = $this->getToken();
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::TOKEN_FIELD_NAME,
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Holt den aktuellen CSRF-Token (oder generiert einen neuen)
     */
    public function getToken(): string
    {
        $tokenData = $this->session->getFramework(self::TOKEN_KEY);

        // Kein Token vorhanden oder abgelaufen
        if (!$tokenData || $this->isTokenExpired($tokenData)) {
            return $this->generateToken();
        }

        return $tokenData['token'];
    }

    /**
     * Prüft ob Token abgelaufen ist
     */
    private function isTokenExpired(array $tokenData): bool
    {
        return time() > $tokenData['expires_at'];
    }

    /**
     * Generiert einen neuen CSRF-Token
     * @throws RandomException
     */
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));

        $this->session->setFramework(self::TOKEN_KEY, [
            'token' => $token,
            'created_at' => time(),
            'expires_at' => time() + $this->config['token_lifetime'],
        ]);

        return $token;
    }

    /**
     * Erstellt Meta-Tag für CSRF-Token (für JavaScript)
     */
    public function getTokenMeta(): string
    {
        $token = $this->getToken();
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Holt Token für JavaScript/AJAX
     */
    public function getTokenForJs(): array
    {
        return [
            'token' => $this->getToken(),
            'field_name' => self::TOKEN_FIELD_NAME,
            'header_name' => self::TOKEN_HEADER_NAME,
        ];
    }

    /**
     * Handle login events
     */
    public function handleLogin(): void
    {
        if ($this->config['regenerate_on_login']) {
            $this->refreshToken();
        }
    }

    /**
     * Erneuert den CSRF-Token (z.B. nach Login)
     */
    public function refreshToken(): string
    {
        $this->clearToken();
        return $this->generateToken();
    }

    /**
     * Löscht den aktuellen Token
     */
    public function clearToken(): void
    {
        $this->session->removeFramework(self::TOKEN_KEY);
    }

    /**
     * Handle logout events
     */
    public function handleLogout(): void
    {
        $this->clearToken();
    }

    /**
     * Enhanced validation with referer check
     */
    public function validateWithReferer(Request $request): bool
    {
        return $this->validateToken($request) && $this->validateReferer($request);
    }

    /**
     * Validiert CSRF-Token aus Request
     */
    public function validateToken(Request $request): bool
    {
        // Skip validation for safe HTTP methods unless configured otherwise
        if (!$this->requiresValidation($request)) {
            return true;
        }

        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return false;
        }

        return $this->isValidToken($token);
    }

    /**
     * Prüft ob CSRF-Validierung erforderlich ist
     */
    public function requiresValidation(Request $request): bool
    {
        $method = strtoupper($request->getMethod()->value);  // ← .value für enum

        // Safe methods don't need CSRF protection unless explicitly configured
        $safeMethods = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

        if (in_array($method, $safeMethods) && !$this->config['require_for_safe_methods']) {
            return false;
        }

        return true;
    }

    /**
     * Extrahiert Token aus Request
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // 1. Try POST body/form data
        $allInput = $request->all();  // ← Verwende all() statt input()
        $token = $allInput[self::TOKEN_FIELD_NAME] ?? null;

        if ($token) {
            return (string)$token;
        }

        // 2. Try headers
        $token = $request->getHeader(self::TOKEN_HEADER_NAME);

        if ($token) {
            return $token;
        }

        // 3. Try alternative header names (lowercase)
        $token = $request->getHeader('x-csrf-token');
        if ($token) {
            return $token;
        }

        $token = $request->getHeader('x-xsrf-token');
        if ($token) {
            return $token;
        }

        return null;
    }

    /**
     * Validiert einen gegebenen Token
     */
    public function isValidToken(string $token): bool
    {
        $tokenData = $this->session->getFramework(self::TOKEN_KEY);

        if (!$tokenData) {
            return false;
        }

        // Check expiration
        if ($this->isTokenExpired($tokenData)) {
            if ($this->config['auto_cleanup_expired']) {
                $this->clearToken();
            }
            return false;
        }

        // Constant-time comparison to prevent timing attacks
        return hash_equals($tokenData['token'], $token);
    }

    /**
     * Validate referer header (additional security layer)
     */
    public function validateReferer(Request $request): bool
    {
        if (!$this->config['strict_referer_check']) {
            return true;
        }

        $referer = $request->getHeader('referer');  // ← getHeader() statt header()
        $host = $request->getHeader('host');        // ← getHeader() statt header()

        if (!$referer || !$host) {
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        return $refererHost === $host;
    }

    /**
     * Get CSRF configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set configuration option
     */
    public function setConfig(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Generate multiple tokens (for forms with multiple submit buttons)
     */
    public function generateMultipleTokens(int $count = 3): array
    {
        $tokens = [];
        $baseToken = $this->getToken();

        for ($i = 0; $i < $count; $i++) {
            $tokens[] = hash('sha256', $baseToken . $i);
        }

        // Store valid tokens
        $this->session->setFramework(self::TOKEN_KEY . '_multi', [
            'tokens' => $tokens,
            'base_token' => $baseToken,
            'created_at' => time(),
            'expires_at' => time() + $this->config['token_lifetime'],
        ]);

        return $tokens;
    }

    /**
     * Validate multiple tokens
     */
    public function validateMultipleTokens(string $token): bool
    {
        $multiTokenData = $this->session->getFramework(self::TOKEN_KEY . '_multi');

        if (!$multiTokenData || $this->isTokenExpired($multiTokenData)) {
            return false;
        }

        return in_array($token, $multiTokenData['tokens'], true);
    }

    /**
     * Clean up expired tokens
     */
    public function cleanup(): void
    {
        $tokenData = $this->session->getFramework(self::TOKEN_KEY);
        $multiTokenData = $this->session->getFramework(self::TOKEN_KEY . '_multi');

        if ($tokenData && $this->isTokenExpired($tokenData)) {
            $this->clearToken();
        }

        if ($multiTokenData && $this->isTokenExpired($multiTokenData)) {
            $this->session->removeFramework(self::TOKEN_KEY . '_multi');
        }
    }

    /**
     * Get debug information
     */
    public function getDebugInfo(): array
    {
        return [
            'config' => $this->config,
            'token_info' => $this->getTokenInfo(),
            'field_name' => self::TOKEN_FIELD_NAME,
            'header_name' => self::TOKEN_HEADER_NAME,
            'session_started' => $this->session->isStarted(),
        ];
    }

    /**
     * Get token information for debugging/monitoring
     */
    public function getTokenInfo(): array
    {
        $tokenData = $this->session->getFramework(self::TOKEN_KEY);

        if (!$tokenData) {
            return [
                'exists' => false,
                'is_expired' => false,
                'created_at' => null,
                'expires_at' => null,
                'remaining_lifetime' => 0,
            ];
        }

        $now = time();
        $isExpired = $this->isTokenExpired($tokenData);

        return [
            'exists' => true,
            'is_expired' => $isExpired,
            'created_at' => $tokenData['created_at'],
            'expires_at' => $tokenData['expires_at'],
            'remaining_lifetime' => max(0, $tokenData['expires_at'] - $now),
        ];
    }
}