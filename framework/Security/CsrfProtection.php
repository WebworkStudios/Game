<?php

/**
 * CSRF Protection Middleware
 * Cross-Site Request Forgery protection for forms
 *
 * File: framework/Security/CsrfProtection.php
 * Directory: /framework/Security/
 */

declare(strict_types=1);

namespace Framework\Security;

class CsrfProtection
{
    private string $tokenName;
    private int $tokenLifetime;

    public function __construct(string $tokenName = 'csrf_token', int $tokenLifetime = 3600)
    {
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

        if (!isset($_SESSION['csrf_tokens'][$token])) {
            $this->sendCsrfError('Invalid CSRF token');
            return false;
        }

        $tokenTime = $_SESSION['csrf_tokens'][$token];

        if (time() - $tokenTime > $this->tokenLifetime) {
            unset($_SESSION['csrf_tokens'][$token]);
            $this->sendCsrfError('CSRF token expired');
            return false;
        }

        // Remove used token
        unset($_SESSION['csrf_tokens'][$token]);

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
        $token = bin2hex(random_bytes(32));

        $_SESSION['csrf_tokens'][$token] = time();

        // Clean old tokens
        $this->cleanExpiredTokens();

        return $token;
    }

    /**
     * Clean expired tokens
     */
    private function cleanExpiredTokens(): void
    {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
            return;
        }

        $currentTime = time();

        foreach ($_SESSION['csrf_tokens'] as $token => $timestamp) {
            if ($currentTime - $timestamp > $this->tokenLifetime) {
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }
    }
}