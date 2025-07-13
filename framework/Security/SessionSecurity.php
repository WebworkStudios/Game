<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\Request;

/**
 * SessionSecurity - Advanced Security Features for Session Management
 * Handles fingerprinting, brute-force protection, and session hijacking prevention
 */
class SessionSecurity
{
    private const string FINGERPRINT_KEY = 'security_fingerprint';
    private const string LOGIN_ATTEMPTS_KEY = 'login_attempts';
    private const string LAST_REGENERATION = 'last_regeneration';
    private const string SECURITY_VIOLATIONS = 'security_violations';

    private array $config;

    public function __construct(
        private readonly Session $session
    )
    {
        $this->config = $this->getDefaultConfig();
    }

    /**
     * Default security configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'min_regeneration_interval' => 300, // 5 minutes
            'max_login_attempts' => 5,
            'login_attempt_window' => 900, // 15 minutes
            'enable_fingerprinting' => true,
            'fingerprint_components' => [
                'user_agent' => true,
                'accept_language' => true,
                'ip_address' => false, // Disabled for mobile users
            ],
            'auto_regenerate_on_ip_change' => false,
            'auto_regenerate_on_user_agent_change' => true,
            'max_security_violations' => 3,
            'violation_lockout_time' => 1800, // 30 minutes
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
     * Validate session security
     */
    public function validateSession(Request $request): array
    {
        $result = [
            'valid' => true,
            'violations' => [],
            'info' => []
        ];

        // Check if session should be regenerated
        if ($this->shouldRegenerateSession()) {
            $this->session->regenerate();
            $result['info'][] = ['type' => 'session_regenerated', 'message' => 'Session ID regenerated for security'];
        }

        // Check session fingerprint
        $this->validateFingerprint($request, $result);

        // Check for security violations lockout
        if ($this->isLockedOutDueToViolations()) {
            $result['valid'] = false;
            $result['violations'][] = ['type' => 'locked_out', 'message' => 'Session locked due to security violations'];
        }

        return $result;
    }

    /**
     * Check if session should be regenerated based on time
     */
    private function shouldRegenerateSession(): bool
    {
        $lastRegeneration = $this->session->getFramework(self::LAST_REGENERATION) ?? 0;
        $interval = $this->config['min_regeneration_interval'];

        return (time() - $lastRegeneration) > $interval;
    }

    /**
     * Validate session fingerprint against stored fingerprint
     */
    private function validateFingerprint(Request $request, array &$result): void
    {
        if (!$this->config['enable_fingerprinting']) {
            return;
        }

        $storedFingerprint = $this->session->getFramework(self::FINGERPRINT_KEY);

        if (!$storedFingerprint) {
            // No fingerprint stored, initialize it
            $this->initializeSecurity($request);
            return;
        }

        $currentFingerprint = $this->generateFingerprint($request);

        if ($storedFingerprint !== $currentFingerprint) {
            $result['valid'] = false;
            $result['violations'][] = ['type' => 'fingerprint_mismatch', 'message' => 'Session fingerprint mismatch detected'];

            // Log potential hijacking attempt
            $this->logSecurityEvent('fingerprint_mismatch', [
                'stored' => $storedFingerprint,
                'current' => $currentFingerprint,
                'ip' => $request->ip(),
                'user_agent' => $request->getHeader('User-Agent'),
            ]);

            $this->recordSecurityViolation();
        }
    }

    /**
     * Initialize session security for new session
     */
    public function initializeSecurity(Request $request): void
    {
        if ($this->config['enable_fingerprinting']) {
            $fingerprint = $this->generateFingerprint($request);
            $this->session->setFramework(self::FINGERPRINT_KEY, $fingerprint);
        }

        $this->session->setFramework(self::LAST_REGENERATION, time());
        $this->clearLoginAttempts();
        $this->clearSecurityViolations();
    }

    /**
     * Generate security fingerprint from request
     */
    public function generateFingerprint(Request $request): string
    {
        $components = [];

        if ($this->config['fingerprint_components']['user_agent']) {
            $components[] = $request->getHeader('User-Agent') ?? '';
        }

        if ($this->config['fingerprint_components']['accept_language']) {
            $components[] = $request->getHeader('Accept-Language') ?? '';
        }

        if ($this->config['fingerprint_components']['ip_address']) {
            $components[] = $request->ip();
        }

        return hash('sha256', implode('|', $components));
    }

    /**
     * Clear login attempts for identifier
     */
    public function clearLoginAttempts(?string $identifier = null): void
    {
        if ($identifier) {
            $this->session->removeFramework(self::LOGIN_ATTEMPTS_KEY . '.' . $identifier);
        } else {
            // Clear all login attempts
            $attempts = $this->session->getFramework(self::LOGIN_ATTEMPTS_KEY) ?? [];
            foreach (array_keys($attempts) as $key) {
                $this->session->removeFramework(self::LOGIN_ATTEMPTS_KEY . '.' . $key);
            }
        }
    }

    /**
     * Record failed login attempt
     */
    public function recordLoginAttempt(string $identifier): int
    {
        $key = self::LOGIN_ATTEMPTS_KEY . '.' . $identifier;
        $attempts = $this->session->getFramework($key) ?? [];

        // Add current attempt
        $attempts[] = time();

        // Clean old attempts outside window
        $window = $this->config['login_attempt_window'];
        $cutoff = time() - $window;
        $attempts = array_filter($attempts, fn($time) => $time > $cutoff);

        $this->session->setFramework($key, $attempts);

        return count($attempts);
    }

    /**
     * Check if identifier is blocked due to too many login attempts
     */
    public function isLoginBlocked(string $identifier): bool
    {
        $key = self::LOGIN_ATTEMPTS_KEY . '.' . $identifier;
        $attempts = $this->session->getFramework($key) ?? [];

        // Clean old attempts
        $window = $this->config['login_attempt_window'];
        $cutoff = time() - $window;
        $attempts = array_filter($attempts, fn($time) => $time > $cutoff);

        return count($attempts) >= $this->config['max_login_attempts'];
    }

    /**
     * Get remaining login attempts for identifier
     */
    public function getRemainingLoginAttempts(string $identifier): int
    {
        if ($this->isLoginBlocked($identifier)) {
            return 0;
        }

        $key = self::LOGIN_ATTEMPTS_KEY . '.' . $identifier;
        $attempts = $this->session->getFramework($key) ?? [];

        // Clean old attempts
        $window = $this->config['login_attempt_window'];
        $cutoff = time() - $window;
        $attempts = array_filter($attempts, fn($time) => $time > $cutoff);

        return max(0, $this->config['max_login_attempts'] - count($attempts));
    }

    /**
     * Record a security violation
     */
    public function recordSecurityViolation(): void
    {
        $violations = $this->session->getFramework(self::SECURITY_VIOLATIONS) ?? [];
        $violations[] = time();

        // Keep only recent violations
        $cutoff = time() - $this->config['violation_lockout_time'];
        $violations = array_filter($violations, fn($time) => $time > $cutoff);

        $this->session->setFramework(self::SECURITY_VIOLATIONS, $violations);
    }

    /**
     * Check if session is locked due to security violations
     */
    public function isLockedOutDueToViolations(): bool
    {
        $violations = $this->session->getFramework(self::SECURITY_VIOLATIONS) ?? [];

        // Clean old violations
        $cutoff = time() - $this->config['violation_lockout_time'];
        $violations = array_filter($violations, fn($time) => $time > $cutoff);

        return count($violations) >= $this->config['max_security_violations'];
    }

    /**
     * Clear security violations
     */
    public function clearSecurityViolations(): void
    {
        $this->session->removeFramework(self::SECURITY_VIOLATIONS);
    }

    /**
     * Log security event
     */
    private function logSecurityEvent(string $event, array $data = []): void
    {
        $logData = [
            'event' => $event,
            'data' => $data,
            'session_id' => $this->session->getId(),
            'timestamp' => time(),
        ];

        error_log('SessionSecurity: ' . json_encode($logData));
    }

    /**
     * Get security statistics
     */
    public function getSecurityStats(): array
    {
        return [
            'fingerprint_enabled' => $this->config['enable_fingerprinting'],
            'last_regeneration' => $this->session->getFramework(self::LAST_REGENERATION),
            'security_violations' => count($this->session->getFramework(self::SECURITY_VIOLATIONS) ?? []),
            'locked_out' => $this->isLockedOutDueToViolations(),
        ];
    }
}