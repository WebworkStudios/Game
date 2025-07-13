<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\Request;

/**
 * SessionSecurity - Centralized Session Fixation Protection
 */
class SessionSecurity
{
    private const string SECURITY_NAMESPACE = 'security';
    private const string LAST_REGENERATION_KEY = 'last_regeneration';
    private const string LOGIN_ATTEMPTS_KEY = 'login_attempts';
    private const string SESSION_FINGERPRINT_KEY = 'fingerprint';
    private const string PRIVILEGE_LEVEL_KEY = 'privilege_level';

    // Security thresholds
    private const int MIN_REGENERATION_INTERVAL = 300; // 5 minutes
    private const int MAX_LOGIN_ATTEMPTS = 5;
    private const int LOGIN_ATTEMPT_WINDOW = 900; // 15 minutes

    public function __construct(
        private readonly Session $session
    )
    {
    }

    /**
     * Handles session security for login events
     */
    public function handleLogin(Request $request, int $userId, string $privilegeLevel = 'user'): void
    {
        // Always regenerate session ID on login
        $this->forceRegenerateSession();

        // Clear any previous security data
        $this->clearSecurityData();

        // Set new security context
        $this->setSecurityData([
            'user_id' => $userId,
            'login_time' => time(),
            'privilege_level' => $privilegeLevel,
            'fingerprint' => $this->generateFingerprint($request),
            'last_activity' => time(),
        ]);

        // Clear failed login attempts
        $this->clearLoginAttempts();
    }

    /**
     * Forces session regeneration regardless of intervals
     */
    public function forceRegenerateSession(): void
    {
        $this->session->regenerate(true);
        $this->setSecurityData(['last_regeneration' => time()]);
    }

    /**
     * Sets security data in session
     */
    private function setSecurityData(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->session->setFramework(self::SECURITY_NAMESPACE . '.' . $key, $value);
        }
    }

    /**
     * Clears all security data
     */
    private function clearSecurityData(): void
    {
        $this->session->setFramework(self::SECURITY_NAMESPACE, []);
    }

    /**
     * Generates session fingerprint based on request
     */
    private function generateFingerprint(Request $request): string
    {
        $components = [
            $request->getUserAgent() ?? '',
            $request->getAcceptLanguage() ?? '',
            // Note: IP is intentionally excluded to allow mobile users
            // to switch networks without session invalidation
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Clears login attempts
     */
    private function clearLoginAttempts(): void
    {
        $this->session->setFramework(self::LOGIN_ATTEMPTS_KEY, []);
    }

    /**
     * Handles session security for logout events
     */
    public function handleLogout(): void
    {
        // Clear all security-related session data
        $this->clearSecurityData();

        // Regenerate session ID to prevent session reuse
        $this->forceRegenerateSession();
    }

    /**
     * Handles failed login attempts
     */
    public function handleFailedLogin(string $identifier): void
    {
        $attempts = $this->getLoginAttempts();
        $currentTime = time();

        // Clean old attempts outside the window
        $attempts = array_filter($attempts, fn($attempt) => $currentTime - $attempt['time'] < self::LOGIN_ATTEMPT_WINDOW
        );

        // Add new attempt
        $attempts[] = [
            'identifier' => $identifier,
            'time' => $currentTime,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];

        $this->setLoginAttempts($attempts);
    }

    /**
     * Gets login attempts data
     */
    private function getLoginAttempts(): array
    {
        return $this->session->getFramework(self::LOGIN_ATTEMPTS_KEY, []);
    }

    /**
     * Sets login attempts data
     */
    private function setLoginAttempts(array $attempts): void
    {
        $this->session->setFramework(self::LOGIN_ATTEMPTS_KEY, $attempts);
    }

    /**
     * Checks if IP/identifier is rate limited
     */
    public function isRateLimited(string $identifier): bool
    {
        $attempts = $this->getLoginAttempts();
        $currentTime = time();

        // Count recent attempts for this identifier
        $recentAttempts = array_filter($attempts, fn($attempt) => $attempt['identifier'] === $identifier &&
            $currentTime - $attempt['time'] < self::LOGIN_ATTEMPT_WINDOW
        );

        return count($recentAttempts) >= self::MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Handles privilege escalation (e.g., user becomes admin)
     */
    public function handlePrivilegeEscalation(string $newPrivilegeLevel): void
    {
        $currentLevel = $this->getSecurityData('privilege_level', 'user');

        if ($newPrivilegeLevel !== $currentLevel) {
            // Regenerate session on privilege change
            $this->forceRegenerateSession();

            // Update privilege level
            $this->setSecurityData([
                'privilege_level' => $newPrivilegeLevel,
                'privilege_changed_at' => time(),
            ]);
        }
    }

    /**
     * Gets security data from session
     */
    private function getSecurityData(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->session->getFramework(self::SECURITY_NAMESPACE, []);
        }

        return $this->session->getFramework(self::SECURITY_NAMESPACE . '.' . $key, $default);
    }

    /**
     * Validates session security (called on each request)
     */
    public function validateSession(Request $request): bool
    {
        // Check if user is logged in
        if (!$this->isAuthenticated()) {
            return true; // No validation needed for guest sessions
        }

        // Validate session fingerprint
        if (!$this->validateFingerprint($request)) {
            $this->handleSecurityViolation('fingerprint_mismatch');
            return false;
        }

        // Update last activity
        $this->updateLastActivity();

        // Check if session should be regenerated due to age
        if ($this->shouldRegenerateByAge()) {
            $this->regenerateSession();
        }

        return true;
    }

    /**
     * Checks if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->getSecurityData('user_id') !== null;
    }

    /**
     * Validates session fingerprint
     */
    private function validateFingerprint(Request $request): bool
    {
        $storedFingerprint = $this->getSecurityData('fingerprint');

        if (!$storedFingerprint) {
            return true; // No fingerprint stored yet
        }

        $currentFingerprint = $this->generateFingerprint($request);
        return hash_equals($storedFingerprint, $currentFingerprint);
    }

    /**
     * Handles security violations
     */
    private function handleSecurityViolation(string $reason): void
    {
        // Log security violation
        error_log("Session security violation: {$reason}");

        // Clear session for security
        $this->session->clear();
        $this->session->regenerate(true);
    }

    /**
     * Updates last activity timestamp
     */
    private function updateLastActivity(): void
    {
        $this->setSecurityData(['last_activity' => time()]);
    }

    /**
     * Checks if session should be regenerated based on age
     */
    private function shouldRegenerateByAge(): bool
    {
        $lastRegeneration = $this->getSecurityData('last_regeneration', 0);
        return (time() - $lastRegeneration) > self::MIN_REGENERATION_INTERVAL;
    }

    /**
     * Regular session regeneration with interval check
     */
    public function regenerateSession(): void
    {
        if ($this->shouldRegenerateByAge()) {
            $this->forceRegenerateSession();
        }
    }

    /**
     * Determines if session ID should be regenerated
     */
    public function shouldRegenerateSession(Request $request): bool
    {
        // Never regenerate for GET requests (performance)
        if ($request->isGet()) {
            return false;
        }

        // Always regenerate for POST requests if enough time has passed
        return $this->shouldRegenerateByAge();
    }

    /**
     * Gets current user ID from session
     */
    public function getUserId(): ?int
    {
        return $this->getSecurityData('user_id');
    }

    /**
     * Gets current privilege level
     */
    public function getPrivilegeLevel(): string
    {
        return $this->getSecurityData('privilege_level', 'guest');
    }
}