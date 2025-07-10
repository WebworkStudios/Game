<?php


declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\Request;
use Random\RandomException;
use RuntimeException;

/**
 * CSRF Protection - Cross-Site Request Forgery Schutz
 */
class Csrf
{
    private const string TOKEN_KEY = 'csrf_token';
    private const string TOKEN_FIELD_NAME = '_token';
    private const string TOKEN_HEADER_NAME = 'X-CSRF-TOKEN';
    private const int TOKEN_LENGTH = 32;
    private const int DEFAULT_LIFETIME = 7200; // 2 Stunden

    public function __construct(
        private readonly Session $session
    )
    {
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
            'expires_at' => time() + self::DEFAULT_LIFETIME,
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
        $this->session->remove('_framework.' . self::TOKEN_KEY);
    }

    /**
     * Validiert Request und wirft Exception bei Fehler
     */
    public function validateOrFail(Request $request): void
    {
        if (!$this->requiresValidation($request)) {
            return;
        }

        if (!$this->validateToken($request)) {
            throw new CsrfException('CSRF token validation failed');
        }
    }

    /**
     * Prüft ob Request CSRF-Validierung benötigt
     */
    public function requiresValidation(Request $request): bool
    {
        // Nur state-changing HTTP-Methoden validieren
        return in_array($request->getMethod()->value, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Validiert CSRF-Token aus Request
     */
    public function validateToken(Request $request): bool
    {
        $requestToken = $this->getTokenFromRequest($request);

        if ($requestToken === null) {
            return false;
        }

        return $this->isValidToken($requestToken);
    }

    /**
     * Extrahiert Token aus Request
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // 1. POST/Form-Daten prüfen
        $token = $request->input(self::TOKEN_FIELD_NAME);
        if ($token !== null) {
            return (string)$token;
        }

        // 2. HTTP-Header prüfen (für AJAX)
        $token = $request->getHeader(strtolower(self::TOKEN_HEADER_NAME));
        if ($token !== null) {
            return $token;
        }

        // 3. JSON-Body prüfen
        $json = $request->json();
        if (isset($json[self::TOKEN_FIELD_NAME])) {
            return (string)$json[self::TOKEN_FIELD_NAME];
        }

        return null;
    }

    /**
     * Validiert einen spezifischen Token
     */
    public function isValidToken(string $token): bool
    {
        $tokenData = $this->session->getFramework(self::TOKEN_KEY);

        if (!$tokenData) {
            return false;
        }

        // Token abgelaufen
        if ($this->isTokenExpired($tokenData)) {
            $this->clearToken();
            return false;
        }

        // Hash-sicherer Vergleich
        return hash_equals($tokenData['token'], $token);
    }

    /**
     * Holt Token-Informationen für Debugging
     */
    public function getTokenInfo(): array
    {
        $tokenData = $this->session->getFramework(self::TOKEN_KEY);

        if (!$tokenData) {
            return [
                'exists' => false,
                'token' => null,
                'created_at' => null,
                'expires_at' => null,
                'is_expired' => null,
                'remaining_time' => null,
            ];
        }

        $now = time();

        return [
            'exists' => true,
            'token' => $tokenData['token'],
            'created_at' => $tokenData['created_at'],
            'expires_at' => $tokenData['expires_at'],
            'is_expired' => $this->isTokenExpired($tokenData),
            'remaining_time' => max(0, $tokenData['expires_at'] - $now),
        ];
    }
}

/**
 * CSRF-Exception
 */
class CsrfException extends RuntimeException
{
    public function __construct(string $message = 'CSRF token validation failed', int $code = 419)
    {
        parent::__construct($message, $code);
    }
}