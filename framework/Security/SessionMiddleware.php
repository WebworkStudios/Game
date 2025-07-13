<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\MiddlewareInterface;

/**
 * SessionMiddleware - Integrates Session Management into Request Lifecycle
 * Handles session start, security validation, and cleanup
 */
class SessionMiddleware implements MiddlewareInterface
{
    private array $config;

    public function __construct(
        private readonly Session         $session,
        private readonly SessionSecurity $sessionSecurity
    )
    {
        $this->config = $this->getDefaultConfig();
    }

    /**
     * Default middleware configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'auto_start' => true,
            'lazy_start' => true,
            'security_validation' => true,
            'auto_regenerate' => true,
            'cleanup_on_response' => true,
            'exempt_paths' => [
                '/api/health',
                '/ping',
            ],
        ];
    }

    /**
     * Set configuration
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Handle request through session middleware
     */
    public function handle(Request $request, callable $next): Response
    {
        try {
            // Check if session handling is needed
            if ($this->shouldSkipSession($request)) {
                return $next($request);
            }

            // Initialize session
            $this->initializeSession($request);

            // Validate session security
            if ($this->config['security_validation']) {
                $validationResult = $this->validateSessionSecurity($request);

                if (!($validationResult['valid'] ?? true)) {
                    return $this->handleSecurityViolation($request, $validationResult);
                }

                // Handle regeneration if required
                if (($validationResult['regeneration_required'] ?? false) && $this->config['auto_regenerate']) {
                    $this->handleSessionRegeneration($request);
                }
            }

            // Process request
            $response = $next($request);

            // Post-process session
            $this->postProcessSession($request, $response);

            return $response;

        } catch (\Throwable $e) {
            // Log error but don't break the application
            $this->logError('Session middleware error', $e);
            return $next($request);
        }
    }

    /**
     * Check if session handling should be skipped
     */
    private function shouldSkipSession(Request $request): bool
    {
        $path = $request->getPath();

        foreach ($this->config['exempt_paths'] as $exemptPath) {
            if (str_starts_with($path, $exemptPath)) {
                return true;
            }
        }

        // Skip for certain request types if configured
        if (!$this->config['auto_start'] && !$this->requiresSession($request)) {
            return true;
        }

        return false;
    }

    /**
     * Check if request requires session
     */
    private function requiresSession(Request $request): bool
    {
        // Session required for:
        // - POST requests (for CSRF)
        // - Authenticated areas
        // - Forms
        // - When session cookie exists

        return $request->getMethod()->value === 'POST'
            || isset($request->getCookies()[session_name()])
            || str_contains($request->getHeader('Content-Type') ?? '', 'form-data')
            || str_contains($request->getPath(), '/auth')
            || str_contains($request->getPath(), '/admin');
    }

    /**
     * Initialize session for request
     */
    private function initializeSession(Request $request): void
    {
        if ($this->config['lazy_start']) {
            // Only start if not already started
            if (!$this->session->isStarted()) {
                $this->session->start();
            }
        } else {
            // Always start
            $this->session->start();
        }

        // Initialize security if new session
        if (!$this->session->hasFramework('security_initialized')) {
            $this->sessionSecurity->initializeSecurity($request);
            $this->session->setFramework('security_initialized', true);
        }
    }

    /**
     * Validate session security
     */
    private function validateSessionSecurity(Request $request): array
    {
        return $this->sessionSecurity->validateSession($request);
    }

    /**
     * Handle security violations
     */
    private function handleSecurityViolation(Request $request, array $result): Response
    {
        $violations = $result['violations'] ?? [];

        // Log security violation
        $this->logSecurityViolation($request, $violations);

        // Handle different violation types
        foreach ($violations as $violation) {
            switch ($violation['type']) {
                case 'session_locked':
                case 'locked_out':
                    return $this->handleSessionLocked($request);

                case 'session_expired':
                    return $this->handleSessionExpired($request);

                case 'fingerprint_mismatch':
                    return $this->handleFingerprintMismatch($request);

                default:
                    return $this->handleGeneralSecurityViolation($request, $violation);
            }
        }

        // Fallback
        return $this->handleGeneralSecurityViolation($request, $violations[0] ?? []);
    }

    /**
     * Log security violation
     */
    private function logSecurityViolation(Request $request, array $violations): void
    {
        $logData = [
            'type' => 'security_violation',
            'violations' => $violations,
            'request' => [
                'method' => $request->getMethod()->value,
                'path' => $request->getPath(),
                'ip' => $request->ip(),
                'user_agent' => $request->getHeader('User-Agent'),
            ],
            'session' => [
                'id' => $this->session->getId(),
                'status' => $this->session->getStatus(),
            ],
            'timestamp' => time(),
        ];

        error_log('Session Security Violation: ' . json_encode($logData));
    }

    /**
     * Handle session locked
     */
    private function handleSessionLocked(Request $request): Response
    {
        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Session locked',
                'message' => 'Session is temporarily locked due to security violations',
                'code' => 'SESSION_LOCKED'
            ], HttpStatus::from(423)); // HTTP 423 Locked
        }

        // Destroy current session
        $this->session->destroy();

        // Redirect to login or error page
        return Response::redirect('/auth/login?reason=session_locked');
    }

    /**
     * Handle session expired
     */
    private function handleSessionExpired(Request $request): Response
    {
        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Session expired',
                'message' => 'Your session has expired. Please log in again.',
                'code' => 'SESSION_EXPIRED'
            ], HttpStatus::UNAUTHORIZED);
        }

        // Clear expired session
        $this->session->destroy();

        // Redirect to login
        return Response::redirect('/auth/login?reason=expired');
    }

    /**
     * Handle fingerprint mismatch
     */
    private function handleFingerprintMismatch(Request $request): Response
    {
        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Security violation',
                'message' => 'Session security validation failed',
                'code' => 'SECURITY_VIOLATION'
            ], HttpStatus::FORBIDDEN);
        }

        // Destroy session for security
        $this->session->destroy();

        // Redirect to login with security warning
        return Response::redirect('/auth/login?reason=security');
    }

    /**
     * Handle general security violation
     */
    private function handleGeneralSecurityViolation(Request $request, array $violation): Response
    {
        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Security violation',
                'message' => $violation['message'] ?? 'Security validation failed',
                'code' => 'SECURITY_VIOLATION'
            ], HttpStatus::FORBIDDEN);
        }

        return Response::redirect('/auth/login?reason=security');
    }

    /**
     * Handle session regeneration
     */
    private function handleSessionRegeneration(Request $request): void
    {
        try {
            // Use Session's built-in regenerate method instead of SessionSecurity
            $this->session->regenerate();
            $this->logSecurityEvent('session_regenerated', $request);
        } catch (\Throwable $e) {
            $this->logError('Session regeneration failed', $e);
        }
    }

    /**
     * Log security event
     */
    private function logSecurityEvent(string $event, Request $request): void
    {
        $logData = [
            'type' => 'security_event',
            'event' => $event,
            'request' => [
                'method' => $request->getMethod()->value,
                'path' => $request->getPath(),
                'ip' => $request->ip(),
            ],
            'session' => [
                'id' => $this->session->getId(),
            ],
            'timestamp' => time(),
        ];

        error_log('Session Security Event: ' . json_encode($logData));
    }

    /**
     * Log general errors
     */
    private function logError(string $message, \Throwable $e): void
    {
        $logData = [
            'type' => 'middleware_error',
            'message' => $message,
            'error' => [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ],
            'timestamp' => time(),
        ];

        error_log('Session Middleware Error: ' . json_encode($logData));
    }

    /**
     * Post-process session after request
     */
    private function postProcessSession(Request $request, Response $response): void
    {
        if (!$this->config['cleanup_on_response']) {
            return;
        }

        try {
            // Clean up expired data
            $this->cleanupExpiredData();

            // Garbage collection (probabilistic)
            if (mt_rand(1, 100) <= 5) { // 5% chance
                $this->session->gc();
            }

        } catch (\Throwable $e) {
            $this->logError('Session cleanup failed', $e);
        }
    }

    /**
     * Clean up expired session data
     */
    private function cleanupExpiredData(): void
    {
        // This could be extended to clean up specific expired data
        // For now, rely on PHP's built-in garbage collection
    }

    /**
     * Public API for manual session operations
     */
    public function forceSessionStart(Request $request): void
    {
        $this->initializeSession($request);
    }

    public function forceSecurityValidation(Request $request): array
    {
        return $this->validateSessionSecurity($request);
    }

    public function forceRegeneration(Request $request): bool
    {
        try {
            $this->handleSessionRegeneration($request);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get middleware status for debugging
     */
    public function getStatus(): array
    {
        return [
            'config' => $this->config,
            'session_started' => $this->session->isStarted(),
            'session_status' => $this->session->getStatus(),
        ];
    }
}