<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\MiddlewareInterface;
use Framework\Routing\RouterCache;
use ReflectionClass;
use ReflectionException;

/**
 * Enhanced CSRF Middleware - Automatische CSRF-Validierung mit SessionSecurity-Integration
 * FIXED: Entfernt spezielle Test-Route-Behandlung, nutzt nur noch Konfiguration
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private array $config;
    private array $exemptPaths;

    public function __construct(
        private readonly Csrf             $csrf,
        private readonly RouterCache      $routerCache,
        private readonly ?SessionSecurity $sessionSecurity = null,
        array                             $config = []
    )
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->exemptPaths = $this->config['exempt_routes'] ?? [];
    }

    /**
     * Default configuration - FIXED: Spezifische Exemptions
     */
    private function getDefaultConfig(): array
    {
        return [
            'exempt_routes' => [
                '/api/*',
                '/webhooks/*',
                '/test/template-functions',
                '/test/validation',
                // NOTE: /test/localization und /test/security sind NICHT exempt!
            ],
            'require_https' => false,
            'auto_cleanup' => true,
            'log_violations' => true,
            'log_successful_validations' => false, // Avoid log spam
            'strict_mode' => false, // If true, reject requests without tokens instead of generating new ones
        ];
    }

    /**
     * Verarbeitet Request und validiert CSRF-Token
     */
    public function handle(Request $request, callable $next): Response
    {
        try {
            // 1. HTTPS-Prüfung in Production
            if (!$this->validateHttpsRequirement($request)) {
                return $this->handleHttpsViolation($request);
            }

            // 2. CSRF-Validierung prüfen
            if ($this->csrf->requiresValidation($request)) {
                if ($this->isExempt($request)) {
                    $this->logExemption($request);
                    return $next($request);
                }

                if (!$this->csrf->validateToken($request)) {
                    $this->logCsrfViolation($request);
                    return $this->handleCsrfFailure($request);
                }

                $this->logSuccessfulValidation($request);
            }

            // 3. Request weiterleiten
            $response = $next($request);

            // 4. Post-processing
            $this->performPostProcessing($request);

            return $response;

        } catch (\Throwable $e) {
            $this->logError($e, $request);
            return $this->handleInternalError($request, $e);
        }
    }

    /**
     * Validates HTTPS requirement in production
     */
    private function validateHttpsRequirement(Request $request): bool
    {
        if (!$this->config['require_https']) {
            return true;
        }

        return $request->isSecure();
    }

    /**
     * Handles HTTPS violation
     */
    private function handleHttpsViolation(Request $request): Response
    {
        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'HTTPS required',
                'message' => 'This action requires a secure connection'
            ], HttpStatus::BAD_REQUEST);
        }

        // Redirect to HTTPS
        $httpsUrl = 'https://' . $request->getHost() . $request->getUri();
        return Response::redirect($httpsUrl, HttpStatus::MOVED_PERMANENTLY);
    }

    /**
     * Prüft ob Request von CSRF-Validierung befreit ist - FIXED: Nur noch Konfiguration
     */
    private function isExempt(Request $request): bool
    {
        $path = $request->getPath();

        // 1. Konfigurierte Pfade prüfen
        foreach ($this->exemptPaths as $exemptPath) {
            if ($this->matchesPath($path, $exemptPath)) {
                return true;
            }
        }

        // 2. Action-Attribute prüfen
        if ($this->hasCsrfExemptAttribute($request)) {
            return true;
        }

        // REMOVED: Spezielle Test-Route-Behandlung - nutze nur noch Konfiguration!

        return false;
    }

    /**
     * Matches path against pattern (supports wildcards)
     */
    private function matchesPath(string $path, string $pattern): bool
    {
        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Wildcard match
        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');
            return str_starts_with($path, $prefix);
        }

        return false;
    }

    /**
     * Prüft ob Action-Klasse das CsrfExempt-Attribute hat
     */
    private function hasCsrfExemptAttribute(Request $request): bool
    {
        try {
            $actionClass = $this->findActionClass($request);

            if ($actionClass === null) {
                return false;
            }

            $reflection = new ReflectionClass($actionClass);
            $attributes = $reflection->getAttributes(CsrfExempt::class);

            return !empty($attributes);

        } catch (ReflectionException $e) {
            $this->logError($e, $request, 'Failed to check CsrfExempt attribute');
            return false; // Bei Fehlern: nicht ausschließen (sicherer)
        }
    }

    /**
     * Findet die Action-Klasse für den aktuellen Request
     */
    private function findActionClass(Request $request): ?string
    {
        try {
            $routes = $this->routerCache->loadRouteEntries();
            $path = $request->getPath();
            $method = $request->getMethod();

            foreach ($routes as $route) {
                if ($route->supportsMethod($method)) {
                    $matches = $route->matches($path);
                    if ($matches !== false) {
                        return $route->action;
                    }
                }
            }

            return null;

        } catch (\Exception $e) {
            $this->logError($e, $request, 'Failed to find action class');
            return null;
        }
    }

    private function logError(\Throwable $e, ?Request $request = null, string $context = ''): void
    {
        $requestInfo = $request ? sprintf(' [%s %s]', $request->getMethod()->value, $request->getPath()) : '';
        $contextInfo = $context ? " ({$context})" : '';

        error_log(sprintf(
            'CSRF Middleware Error%s%s: %s in %s:%d',
            $requestInfo,
            $contextInfo,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }

    private function logExemption(Request $request): void
    {
        if (!$this->config['log_violations']) {
            return;
        }

        error_log(sprintf(
            'CSRF exemption: %s %s',
            $request->getMethod()->value,
            $request->getPath()
        ));
    }

    /**
     * Enhanced logging methods
     */
    private function logCsrfViolation(Request $request): void
    {
        if (!$this->config['log_violations']) {
            return;
        }

        error_log(sprintf(
            'CSRF violation: %s %s from %s (User-Agent: %s)',
            $request->getMethod()->value,
            $request->getPath(),
            $request->ip(),
            $request->getUserAgent() ?? 'unknown'
        ));
    }

    private function logSuccessfulValidation(Request $request): void
    {
        if (!$this->config['log_successful_validations']) {
            return;
        }

        error_log(sprintf(
            'CSRF validation successful: %s %s',
            $request->getMethod()->value,
            $request->getPath()
        ));
    }

    /**
     * Behandelt CSRF-Validierungsfehler
     */
    private function handleCsrfFailure(Request $request): Response
    {
        // JSON-Response für API-Requests
        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'CSRF token validation failed',
                'message' => 'The request could not be completed due to invalid security token.',
                'code' => 'CSRF_TOKEN_MISMATCH'
            ], HttpStatus::PAGE_EXPIRED);
        }

        // HTML-Response für normale Requests
        return Response::serverError('Security validation failed');
    }

    /**
     * Performs post-request processing
     */
    private function performPostProcessing(Request $request): void
    {
        // Auto-cleanup: Check if token is expired and clean it up
        if ($this->config['auto_cleanup']) {
            $this->cleanupExpiredTokens();
        }

        // Handle session security integration
        if ($this->sessionSecurity) {
            $this->handleSessionSecurityIntegration($request);
        }
    }

    /**
     * Cleans up expired CSRF tokens
     */
    private function cleanupExpiredTokens(): void
    {
        try {
            $tokenInfo = $this->csrf->getTokenInfo();

            // If token exists and is expired, clear it
            if ($tokenInfo['exists'] && $tokenInfo['is_expired']) {
                $this->csrf->clearToken();
            }
        } catch (\Throwable $e) {
            // Log error but don't break the application
            error_log('CSRF token cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Handles session security integration
     */
    private function handleSessionSecurityIntegration(Request $request): void
    {
        // This integration allows CSRF middleware to work with session security
        // for things like privilege escalation detection, etc.
        // Implementation depends on specific requirements
    }

    /**
     * Handles internal errors gracefully
     */
    private function handleInternalError(Request $request, \Throwable $e): Response
    {
        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred during security validation'
            ], HttpStatus::INTERNAL_SERVER_ERROR);
        }

        // Return basic error page using existing Response method
        return Response::serverError('Security validation failed');
    }

    /**
     * Public API for external integration
     */
    public function refreshTokenOnLogin(): void
    {
        $this->csrf->refreshToken();
    }

    public function refreshTokenOnLogout(): void
    {
        $this->csrf->refreshToken();
    }

    public function isTokenValid(string $token): bool
    {
        return $this->csrf->isValidToken($token);
    }

    /**
     * Debug information
     */
    public function getDebugInfo(): array
    {
        return [
            'config' => $this->config,
            'exempt_paths' => $this->exemptPaths,
            'token_info' => $this->csrf->getTokenInfo(),
            'session_security_available' => $this->sessionSecurity !== null,
        ];
    }
}