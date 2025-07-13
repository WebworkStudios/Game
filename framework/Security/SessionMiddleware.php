<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\MiddlewareInterface;

/**
 * Session Middleware - Enhanced with Session Security and Fixation Protection
 */
class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Session         $session,
        private readonly SessionSecurity $sessionSecurity
    )
    {
    }

    /**
     * Verarbeitet Request mit erweiterten Security-Features
     */
    public function handle(Request $request, callable $next): Response
    {
        try {
            // 1. Session starten
            $this->session->start();

            // 2. Security-Validierung durchführen
            if (!$this->validateSessionSecurity($request)) {
                return $this->handleSecurityViolation($request);
            }

            // 3. Session-ID-Regenerierung prüfen
            $this->handleSessionRegeneration($request);

            // 4. Request weiterleiten
            $response = $next($request);

            // 5. Flash-Messages verarbeiten
            $this->handleFlashMessages();

            // 6. Session-Cleanup nach Response
            $this->cleanupSession();

            return $response;

        } catch (\Throwable $e) {
            // Bei Session-Fehlern: Session zurücksetzen und Fehler loggen
            error_log("Session middleware error: " . $e->getMessage());

            $this->session->clear();
            $this->session->regenerate(true);

            // Request trotzdem weiterleiten (ohne Session)
            return $next($request);
        }
    }

    /**
     * Validiert Session-Sicherheit
     */
    private function validateSessionSecurity(Request $request): bool
    {
        // Rate-Limiting für Login-Versuche prüfen
        if ($this->isLoginRequest($request)) {
            $identifier = $this->getLoginIdentifier($request);

            if ($identifier && $this->sessionSecurity->isRateLimited($identifier)) {
                return false;
            }
        }

        // Session-Fingerprint und andere Security-Checks
        return $this->sessionSecurity->validateSession($request);
    }

    /**
     * Prüft ob Request ein Login-Versuch ist
     */
    private function isLoginRequest(Request $request): bool
    {
        // POST-Request mit Login-Daten
        if (!$request->isPost()) {
            return false;
        }

        // Verschiedene Login-Endpunkte prüfen
        $path = $request->getPath();
        $loginPaths = ['/login', '/auth/login', '/api/login'];

        foreach ($loginPaths as $loginPath) {
            if (str_starts_with($path, $loginPath)) {
                return true;
            }
        }

        // POST-Daten auf Login-Fields prüfen
        $postData = $request->getPost();
        return isset($postData['email']) || isset($postData['username']) || isset($postData['login']);
    }

    /**
     * Extrahiert Login-Identifier für Rate-Limiting
     */
    private function getLoginIdentifier(Request $request): ?string
    {
        $postData = $request->getPost();

        // Email oder Username als Identifier verwenden
        return $postData['email'] ?? $postData['username'] ?? $postData['login'] ?? null;
    }

    /**
     * Behandelt Security-Verletzungen
     */
    private function handleSecurityViolation(Request $request): Response
    {
        // Rate-Limited: Too Many Requests
        if ($this->isLoginRequest($request)) {
            $identifier = $this->getLoginIdentifier($request);

            if ($identifier && $this->sessionSecurity->isRateLimited($identifier)) {
                return $this->createRateLimitResponse();
            }
        }

        // Session-Fingerprint-Verletzung: Session zerstören und Unauthorized
        return $this->createSecurityViolationResponse();
    }

    /**
     * Erstellt Rate-Limit-Response
     */
    private function createRateLimitResponse(): Response
    {
        $retryAfter = 900; // 15 Minuten

        if ($this->isJsonRequest()) {
            return Response::json([
                'error' => 'Too many login attempts',
                'message' => 'Please try again later',
                'retry_after' => $retryAfter
            ], HttpStatus::TOO_MANY_REQUESTS)
                ->withHeader('Retry-After', (string)$retryAfter);
        }

        // HTML-Response für normale Requests
        $html = $this->renderRateLimitPage($retryAfter);
        return new Response(
            HttpStatus::TOO_MANY_REQUESTS,
            ['Retry-After' => (string)$retryAfter],
            $html
        );
    }

    /**
     * Prüft ob Request JSON erwartet
     */
    private function isJsonRequest(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

        return str_contains($contentType, 'application/json') ||
            str_contains($acceptHeader, 'application/json');
    }

    /**
     * Rendert Rate-Limit-Seite
     */
    private function renderRateLimitPage(int $retryAfter): string
    {
        $minutes = ceil($retryAfter / 60);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Too Many Attempts</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .container { max-width: 500px; margin: 0 auto; }
        .error-code { font-size: 72px; font-weight: bold; color: #e74c3c; }
        .message { font-size: 18px; margin: 20px 0; }
        .retry-info { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">429</div>
        <h1>Too Many Login Attempts</h1>
        <p class="message">You have exceeded the maximum number of login attempts.</p>
        <div class="retry-info">
            <strong>Please try again in {$minutes} minutes.</strong>
        </div>
        <p>This security measure protects against brute force attacks.</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Erstellt Security-Violation-Response
     */
    private function createSecurityViolationResponse(): Response
    {
        if ($this->isJsonRequest()) {
            return Response::json([
                'error' => 'Session security violation',
                'message' => 'Please refresh and try again'
            ], HttpStatus::UNAUTHORIZED);
        }

        // Redirect zu Login-Seite für normale Requests
        return Response::redirect('/login');
    }

    /**
     * Handhabt Session-ID-Regenerierung
     */
    private function handleSessionRegeneration(Request $request): void
    {
        // Prüfen ob Session regeneriert werden soll
        if ($this->sessionSecurity->shouldRegenerateSession($request)) {
            $this->sessionSecurity->regenerateSession();
        }
    }

    /**
     * Verwaltet Flash-Messages
     */
    private function handleFlashMessages(): void
    {
        // Flash-Messages werden automatisch von Session-Klasse verwaltet
        // Hier könnten zusätzliche Flash-Message-Logiken implementiert werden
    }

    /**
     * Session-Cleanup nach Request
     */
    private function cleanupSession(): void
    {
        // Expired CSRF-Tokens entfernen
        $this->cleanupExpiredTokens();

        // Alte Login-Attempts bereinigen
        $this->cleanupOldLoginAttempts();
    }

    /**
     * Bereinigt abgelaufene CSRF-Tokens
     */
    private function cleanupExpiredTokens(): void
    {
        // Diese Logik könnte in Csrf-Klasse ausgelagert werden
        // Hier als Beispiel implementiert
        $frameworkData = $this->session->getFramework('csrf_token');

        if (is_array($frameworkData) && isset($frameworkData['expires_at'])) {
            if (time() > $frameworkData['expires_at']) {
                $this->session->removeFramework('csrf_token');
            }
        }
    }

    /**
     * Bereinigt alte Login-Attempts
     */
    private function cleanupOldLoginAttempts(): void
    {
        $attempts = $this->session->getFramework('login_attempts', []);

        if (!is_array($attempts)) {
            return;
        }

        $currentTime = time();
        $cleanedAttempts = array_filter($attempts, function ($attempt) use ($currentTime) {
            return isset($attempt['time']) && ($currentTime - $attempt['time']) < 900; // 15 Minuten
        });

        // Nur aktualisieren wenn sich was geändert hat
        if (count($cleanedAttempts) !== count($attempts)) {
            $this->session->setFramework('login_attempts', array_values($cleanedAttempts));
        }
    }

    /**
     * Debug-Information für Development
     */
    public function getDebugInfo(): array
    {
        if (!$this->session->isStarted()) {
            return ['session' => 'not_started'];
        }

        return [
            'session_id' => $this->session->getId(),
            'is_authenticated' => $this->sessionSecurity->isAuthenticated(),
            'user_id' => $this->sessionSecurity->getUserId(),
            'privilege_level' => $this->sessionSecurity->getPrivilegeLevel(),
            'session_data' => $this->session->all(),
        ];
    }
}