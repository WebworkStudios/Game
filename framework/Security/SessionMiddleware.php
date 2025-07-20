<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\MiddlewareInterface;
use Throwable;

/**
 * SessionMiddleware - Request Orchestration Layer
 *
 * Verantwortlichkeiten:
 * - Request-Flow Orchestrierung
 * - Session Start/Stop Management im Request-Zyklus
 * - Security Validation Triggering
 * - Error-Handler für Security Violations
 */
class SessionMiddleware implements MiddlewareInterface
{
    private array $config;

    public function __construct(
        private readonly Session         $session,
        private readonly SessionSecurity $sessionSecurity,
        private readonly ResponseFactory $responseFactory
    )
    {
        $this->config = $this->getDefaultConfig();
    }

    private function getDefaultConfig(): array
    {
        return [
            'auto_start' => true,
            'security_validation' => true,
            'exempt_paths' => [
                '/api/health',
                '/ping',
                '/favicon.ico',
            ],
            'require_session_paths' => [
                '/admin',
                '/dashboard',
                '/team',
                '/player',
            ],
        ];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    public function handle(Request $request, callable $next): Response
    {
        try {
            // 1. Check if session needed
            if ($this->shouldSkipSession($request)) {
                return $next($request);
            }

            // 2. Start session
            $this->startSession($request);

            // 3. Validate security if enabled
            if ($this->config['security_validation']) {
                $validation = $this->sessionSecurity->validateSession($request);

                if (!$validation['valid']) {
                    return $this->handleSecurityViolation($request, $validation);
                }

                // Handle required actions
                $this->handleSecurityActions($validation['actions'] ?? []);
            }

            // 4. Process request
            $response = $next($request);

            // 5. Cleanup (if needed)
            $this->cleanup();

            return $response;

        } catch (Throwable $e) {
            $this->logError('Session middleware error', $e);
            // Continue without session on errors
            return $next($request);
        }
    }

    private function shouldSkipSession(Request $request): bool
    {
        $path = $request->getPath();

        // Check exempt paths
        foreach ($this->config['exempt_paths'] as $exemptPath) {
            if (str_starts_with($path, $exemptPath)) {
                return true;
            }
        }

        // Skip if auto_start disabled and session not required
        if (!$this->config['auto_start'] && !$this->requiresSession($request)) {
            return false;
        }

        return false;
    }

    private function requiresSession(Request $request): bool
    {
        $path = $request->getPath();

        // Check if path requires session
        foreach ($this->config['require_session_paths'] as $requiredPath) {
            if (str_starts_with($path, $requiredPath)) {
                return true;
            }
        }

        return $request->getMethod()->value === 'POST'
            || isset($request->getCookies()[session_name()])
            || str_contains($request->getHeader('Content-Type') ?? '', 'form-data')
            || str_contains($path, '/auth');
    }

    private function startSession(Request $request): void
    {
        if (!$this->session->isStarted()) {
            $this->session->start();

            // Initialize security for new sessions
            if (!$this->session->hasFramework('security_initialized')) {
                $this->sessionSecurity->initializeSession($request);
                $this->session->setFramework('security_initialized', true);
            }
        }
    }


    private function handleSecurityViolation(Request $request, array $validation): Response
    {
        $violations = $validation['violations'] ?? [];

        // Handle first violation type (most critical)
        $violation = $violations[0] ?? [];

        return match ($violation['type'] ?? 'unknown') {
            'session_expired' => $this->handleSessionExpired($request),
            'fingerprint_mismatch' => $this->handleFingerprintMismatch($request),
            'locked_out' => $this->handleSessionLocked($request),
            default => $this->handleGeneralSecurityViolation($request, $violation),
        };
    }


    private function handleSessionExpired(Request $request): Response
    {
        $this->session->destroy();

        if ($request->expectsJson()) {
            return $this->responseFactory->json([
                'error' => 'Session expired',
                'message' => 'Your session has expired. Please log in again.',
                'code' => 'SESSION_EXPIRED'
            ], HttpStatus::UNAUTHORIZED);
        }

        return $this->responseFactory->redirect('/auth/login?reason=expired');
    }

    private function handleFingerprintMismatch(Request $request): Response
    {
        $this->session->destroy();

        if ($request->expectsJson()) {
            return $this->responseFactory->json([
                'error' => 'Security violation',
                'message' => 'Session security validation failed',
                'code' => 'SECURITY_VIOLATION'
            ], HttpStatus::FORBIDDEN);
        }

        return $this->responseFactory->redirect('/auth/login?reason=security');
    }

    private function handleSessionLocked(Request $request): Response
    {
        if ($request->expectsJson()) {
            return $this->responseFactory->json([
                'error' => 'Session locked',
                'message' => 'Session is temporarily locked due to security violations',
                'code' => 'SESSION_LOCKED'
            ], HttpStatus::from(423)); // HTTP 423 Locked
        }

        return $this->responseFactory->redirect('/auth/login?reason=locked');
    }

    private function handleGeneralSecurityViolation(Request $request, array $violation): Response
    {
        if ($request->expectsJson()) {
            return $this->responseFactory->json([
                'error' => 'Security violation',
                'message' => $violation['message'] ?? 'Security validation failed',
                'code' => 'SECURITY_VIOLATION'
            ], HttpStatus::FORBIDDEN);
        }

        return $this->responseFactory->redirect('/auth/login?reason=security');
    }

    private function handleSecurityActions(array $actions): void
    {
        foreach ($actions as $action) {
            match ($action) {
                'regenerate_session' => $this->sessionSecurity->regenerateSession('security_interval'),
                default => null,
            };
        }
    }

    private function cleanup(): void
    {
        if (mt_rand(1, 100) <= 5) {
            try {
                $this->session->gc();
            } catch (Throwable $e) {
                $this->logError('Session garbage collection failed', $e);
            }
        }
    }

    private function logError(string $message, Throwable $e): void
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

        error_log('SessionMiddleware: ' . json_encode($logData));
    }

    public function forceSessionStart(Request $request): void
    {
        $this->startSession($request);
    }

    public function forceSecurityValidation(Request $request): array
    {
        return $this->sessionSecurity->validateSession($request);
    }

    public function forceRegeneration(Request $request, string $reason = 'manual'): bool
    {
        try {
            return $this->sessionSecurity->regenerateSession($reason);
        } catch (Throwable $e) {
            $this->logError('Force regeneration failed', $e);
            return false;
        }
    }
}
