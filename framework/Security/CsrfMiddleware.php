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
     * Default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'exempt_routes' => [
                '/api/*',
                '/webhooks/*',
                '/test/*',
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
            ], HttpStatus::BAD_REQUEST); // Use BAD_REQUEST instead of UPGRADE_REQUIRED
        }

        // Redirect to HTTPS
        $httpsUrl = 'https://' . $request->getHost() . $request->getUri();
        return Response::redirect($httpsUrl, HttpStatus::MOVED_PERMANENTLY);
    }

    /**
     * Prüft ob Request von CSRF-Validierung ausgenommen ist
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

        // 3. Spezielle Test-Route-Behandlung
        if ($this->isTestRoute($request)) {
            return $this->shouldExemptTestRoute($request);
        }

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

    /**
     * Special handling for test routes
     */
    private function isTestRoute(Request $request): bool
    {
        return str_starts_with($request->getPath(), '/test/');
    }

    /**
     * Determines if test route should be exempt
     */
    private function shouldExemptTestRoute(Request $request): bool
    {
        $path = $request->getPath();

        // Security test route should NOT be exempt (we want to test CSRF)
        if ($path === '/test/security') {
            return false;
        }

        // Other test routes can be exempt
        return true;
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
                'code' => 419,
                'new_token' => $this->generateNewTokenForResponse()
            ], HttpStatus::PAGE_EXPIRED);
        }

        // HTML-Response für Web-Requests
        return $this->createCsrfErrorResponse($request);
    }

    /**
     * Rendert erweiterte CSRF-Fehlerseite
     */

    /**
     * Generates new token for failed requests (if not in strict mode)
     */
    private function generateNewTokenForResponse(): ?string
    {
        if ($this->config['strict_mode']) {
            return null;
        }

        try {
            return $this->csrf->generateToken();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Erstellt CSRF-Fehler Response
     */
    private function createCsrfErrorResponse(Request $request): Response
    {
        $html = $this->renderCsrfErrorPage($request);

        return new Response(
            status: HttpStatus::PAGE_EXPIRED,
            headers: [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate'
            ],
            body: $html
        );
    }

    /**
     * Rendert erweiterte CSRF-Fehlerseite
     */
    private function renderCsrfErrorPage(Request $request): string
    {
        $newToken = $this->generateNewTokenForResponse();
        $tokenMeta = $newToken ? "<meta name='csrf-token' content='{$newToken}'>" : '';

        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <title>419 - CSRF Token Mismatch</title>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            {$tokenMeta}
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #333;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    max-width: 600px;
                    width: 90%;
                    text-align: center;
                }
                .code { 
                    font-size: 4em; 
                    font-weight: bold; 
                    color: #e74c3c; 
                    margin-bottom: 20px;
                    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
                }
                .title { 
                    font-size: 1.5em; 
                    margin-bottom: 15px; 
                    color: #333;
                    font-weight: 600;
                }
                .message {
                    color: #666;
                    margin-bottom: 25px;
                    line-height: 1.6;
                    font-size: 1.1em;
                }
                .details {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                    border-left: 4px solid #e74c3c;
                    text-align: left;
                }
                .details h4 {
                    color: #e74c3c;
                    margin-bottom: 10px;
                    font-size: 1.1em;
                }
                .details ul {
                    color: #555;
                    line-height: 1.5;
                    padding-left: 20px;
                }
                .details li {
                    margin: 8px 0;
                }
                .actions {
                    margin-top: 30px;
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .btn {
                    padding: 12px 24px;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 1em;
                    text-decoration: none;
                    display: inline-block;
                    transition: all 0.3s ease;
                    font-weight: 500;
                }
                .btn-primary {
                    background: #007bff;
                    color: white;
                }
                .btn-primary:hover {
                    background: #0056b3;
                    transform: translateY(-1px);
                }
                .btn-secondary {
                    background: #6c757d;
                    color: white;
                }
                .btn-secondary:hover {
                    background: #545b62;
                    transform: translateY(-1px);
                }
                .security-info {
                    background: #e3f2fd;
                    border: 1px solid #bbdefb;
                    border-radius: 8px;
                    padding: 15px;
                    margin: 20px 0;
                    color: #1565c0;
                    font-size: 0.9em;
                }
                @media (max-width: 480px) {
                    .container { padding: 20px; }
                    .code { font-size: 3em; }
                    .actions { flex-direction: column; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='code'>419</div>
                <h1 class='title'>CSRF Token Mismatch</h1>
                <p class='message'>Your session has expired or the security token is invalid. This protection prevents unauthorized requests.</p>
                
                <div class='details'>
                    <h4>🔒 Why did this happen?</h4>
                    <ul>
                        <li>Your session may have expired</li>
                        <li>You might have opened this page in multiple tabs</li>
                        <li>Your browser cookies could be disabled</li>
                        <li>The security token was modified or corrupted</li>
                    </ul>
                </div>

                <div class='security-info'>
                    <strong>🛡️ Security Note:</strong> This protection helps prevent Cross-Site Request Forgery (CSRF) attacks and keeps your data safe.
                </div>
                
                <div class='actions'>
                    <button onclick='refreshPage()' class='btn btn-primary' id='refreshBtn'>🔄 Refresh Page</button>
                    <a href='/' class='btn btn-secondary'>🏠 Go Home</a>
                </div>
            </div>

            <script>
                // Auto-refresh countdown
                let countdown = 10;
                const refreshBtn = document.getElementById('refreshBtn');
                const originalText = refreshBtn.textContent;
                
                function refreshPage() {
                    window.location.reload();
                }
                
                const timer = setInterval(() => {
                    refreshBtn.textContent = `🔄 Refresh Page (\${countdown}s)`;
                    countdown--;
                    
                    if (countdown < 0) {
                        clearInterval(timer);
                        refreshPage();
                    }
                }, 1000);
                
                // Stop countdown on user interaction
                document.addEventListener('click', () => {
                    clearInterval(timer);
                    refreshBtn.textContent = originalText;
                });
                
                document.addEventListener('keypress', () => {
                    clearInterval(timer);
                    refreshBtn.textContent = originalText;
                });

                // Update CSRF token if provided
                if (document.querySelector('meta[name=\"csrf-token\"]')) {
                    console.log('New CSRF token provided for retry');
                }
            </script>
        </body>
        </html>";
    }

    private function logSuccessfulValidation(Request $request): void
    {
        // Only log successful validations if explicitly enabled in config
        // This avoids log spam in production
        if ($this->config['log_successful_validations'] ?? false) {
            error_log(sprintf(
                'CSRF validation successful: %s %s',
                $request->getMethod()->value,
                $request->getPath()
            ));
        }
    }

    /**
     * Post-processing after successful request
     */
    private function performPostProcessing(Request $request): void
    {
        if ($this->config['auto_cleanup']) {
            $this->cleanupExpiredTokens();
        }

        // Integrate with SessionSecurity for login/logout events
        if ($this->sessionSecurity) {
            $this->handleSessionSecurityIntegration($request);
        }
    }

    /**
     * Cleans up expired tokens
     */
    private function cleanupExpiredTokens(): void
    {
        try {
            // Get token info and check if expired
            $tokenInfo = $this->csrf->getTokenInfo();

            if ($tokenInfo['exists'] && $tokenInfo['is_expired']) {
                $this->csrf->clearToken();
            }
        } catch (\Exception $e) {
            $this->logError($e, null, 'Token cleanup failed');
        }
    }

    /**
     * Integrates with SessionSecurity for token management
     */
    private function handleSessionSecurityIntegration(Request $request): void
    {
        // Check if this was a login request that succeeded
        if ($this->isSuccessfulLoginRequest($request)) {
            $this->csrf->refreshToken();
            $this->logTokenRefresh('login', $request);
        }

        // Check if this was a logout request
        if ($this->isLogoutRequest($request)) {
            $this->csrf->refreshToken();
            $this->logTokenRefresh('logout', $request);
        }

        // Check for privilege escalation
        if ($this->sessionSecurity->isAuthenticated()) {
            $this->handlePrivilegeChange($request);
        }
    }

    /**
     * Checks if request was a successful login
     */
    private function isSuccessfulLoginRequest(Request $request): bool
    {
        if (!$request->isPost()) {
            return false;
        }

        $path = $request->getPath();
        $loginPaths = ['/login', '/auth/login', '/api/login'];

        foreach ($loginPaths as $loginPath) {
            if (str_starts_with($path, $loginPath)) {
                // Check if SessionSecurity indicates successful authentication
                return $this->sessionSecurity && $this->sessionSecurity->isAuthenticated();
            }
        }

        return false;
    }

    private function logTokenRefresh(string $reason, Request $request): void
    {
        error_log(sprintf(
            'CSRF token refreshed (%s): %s %s',
            $reason,
            $request->getMethod()->value,
            $request->getPath()
        ));
    }

    /**
     * Checks if request was a logout
     */
    private function isLogoutRequest(Request $request): bool
    {
        $path = $request->getPath();
        $logoutPaths = ['/logout', '/auth/logout', '/api/logout'];

        foreach ($logoutPaths as $logoutPath) {
            if (str_starts_with($path, $logoutPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handles privilege level changes
     */
    private function handlePrivilegeChange(Request $request): void
    {
        // This would require tracking previous privilege level
        // Implementation depends on how privilege escalation is detected
        // For now, this is a placeholder for future enhancement
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