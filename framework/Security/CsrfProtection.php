<?php
/**
 * CSRF Protection Middleware
 * Cross-Site Request Forgery protection for forms using SessionManager
 */

declare(strict_types=1);

namespace Framework\Security;

use Framework\Core\SessionManagerInterface;

class CsrfProtection
{
    private string $tokenName;
    private int $tokenLifetime;
    private SessionManagerInterface $session;

    public function __construct(
        SessionManagerInterface $session,
        string $tokenName = 'csrf_token',
        int $tokenLifetime = 3600
    ) {
        $this->session = $session;
        $this->tokenName = $tokenName;
        $this->tokenLifetime = $tokenLifetime;
    }

    /**
     * Handle CSRF protection
     */
    public function handle(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return true;
        }

        return $this->validateToken();
    }

    /**
     * Validate CSRF token
     */
    private function validateToken(): bool
    {
        $token = $_POST[$this->tokenName] ?? $_GET[$this->tokenName] ?? '';

        if (empty($token)) {
            $this->sendCsrfError('CSRF token missing');
            return false;
        }

        $csrfTokens = $this->session->get('csrf_tokens', []);

        if (!is_array($csrfTokens) || !isset($csrfTokens[$token])) {
            $this->sendCsrfError('Invalid CSRF token');
            return false;
        }

        $tokenTime = $csrfTokens[$token];

        if (!is_numeric($tokenTime) || (time() - $tokenTime) > $this->tokenLifetime) {
            // Remove expired token
            unset($csrfTokens[$token]);
            $this->session->set('csrf_tokens', $csrfTokens);
            $this->sendCsrfError('CSRF token expired');
            return false;
        }

        // Remove used token (one-time use)
        unset($csrfTokens[$token]);
        $this->session->set('csrf_tokens', $csrfTokens);

        return true;
    }

    /**
     * Send CSRF error response
     */
    private function sendCsrfError(string $message): void
    {
        http_response_code(403);

        if ($this->isJsonRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
        } else {
            echo "403 Forbidden: {$message}";
        }

        exit;
    }

    /**
     * Check if request expects JSON response
     */
    private function isJsonRequest(): bool
    {
        $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return str_contains($contentType, 'application/json') ||
            str_contains($accept, 'application/json');
    }

    /**
     * Generate CSRF token
     */
    public function generateToken(): string
    {
        $csrfTokens = $this->session->get('csrf_tokens', []);

        if (!is_array($csrfTokens)) {
            $csrfTokens = [];
        }

        $token = bin2hex(random_bytes(32));
        $csrfTokens[$token] = time();

        // Clean old tokens to prevent memory bloat
        $this->cleanExpiredTokens($csrfTokens);

        // Limit number of tokens per session (prevent memory exhaustion)
        if (count($csrfTokens) > 10) {
            $oldestToken = array_key_first($csrfTokens);
            unset($csrfTokens[$oldestToken]);
        }

        $this->session->set('csrf_tokens', $csrfTokens);

        return $token;
    }

    /**
     * Clean expired tokens
     */
    private function cleanExpiredTokens(array &$csrfTokens): void
    {
        $currentTime = time();

        foreach ($csrfTokens as $token => $timestamp) {
            if (!is_numeric($timestamp) || ($currentTime - $timestamp) > $this->tokenLifetime) {
                unset($csrfTokens[$token]);
            }
        }
    }

    /**
     * Get token name for forms
     */
    public function getTokenName(): string
    {
        return $this->tokenName;
    }

    /**
     * Get token lifetime
     */
    public function getTokenLifetime(): int
    {
        return $this->tokenLifetime;
    }

    /**
     * Generate hidden input field for forms
     */
    public function getTokenField(): string
    {
        $token = $this->generateToken();
        $name = htmlspecialchars($this->tokenName, ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

        return "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\">";
    }

    /**
     * Get token for AJAX requests
     */
    public function getTokenForAjax(): array
    {
        return [
            'name' => $this->tokenName,
            'value' => $this->generateToken()
        ];
    }

    /**
     * Validate token programmatically (without HTTP response)
     */
    public function isValidToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $csrfTokens = $this->session->get('csrf_tokens', []);

        if (!is_array($csrfTokens) || !isset($csrfTokens[$token])) {
            return false;
        }

        $tokenTime = $csrfTokens[$token];

        if (!is_numeric($tokenTime) || (time() - $tokenTime) > $this->tokenLifetime) {
            // Remove expired token
            unset($csrfTokens[$token]);
            $this->session->set('csrf_tokens', $csrfTokens);
            return false;
        }

        return true;
    }

    /**
     * Remove specific token (for manual cleanup)
     */
    public function removeToken(string $token): void
    {
        $csrfTokens = $this->session->get('csrf_tokens', []);

        if (is_array($csrfTokens) && isset($csrfTokens[$token])) {
            unset($csrfTokens[$token]);
            $this->session->set('csrf_tokens', $csrfTokens);
        }
    }

    /**
     * Clear all CSRF tokens
     */
    public function clearAllTokens(): void
    {
        $this->session->set('csrf_tokens', []);
    }

    /**
     * Get statistics about current tokens
     */
    public function getTokenStats(): array
    {
        $csrfTokens = $this->session->get('csrf_tokens', []);

        if (!is_array($csrfTokens)) {
            return ['total' => 0, 'expired' => 0, 'valid' => 0];
        }

        $currentTime = time();
        $expired = 0;
        $valid = 0;

        foreach ($csrfTokens as $timestamp) {
            if (is_numeric($timestamp) && ($currentTime - $timestamp) <= $this->tokenLifetime) {
                $valid++;
            } else {
                $expired++;
            }
        }

        return [
            'total' => count($csrfTokens),
            'expired' => $expired,
            'valid' => $valid
        ];
    }
}