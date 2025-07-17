<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\Request;

/**
 * SessionSecurity - Security Layer für Session Management
 *
 * Verantwortlichkeiten:
 * - Fingerprinting (Generate, Validate, Store)
 * - Regeneration Logic (Timing, Triggers)
 * - Security Validation (Fingerprint-Check, Lockouts)
 * - Login Attempts Tracking
 * - Security Event Logging
 */
class SessionSecurity
{
    private const string FINGERPRINT_KEY = 'security_fingerprint';
    private const string LOGIN_ATTEMPTS_KEY = 'login_attempts';
    private const string LAST_REGENERATION = 'last_regeneration';
    private const string SECURITY_VIOLATIONS = 'security_violations';
    private const string LAST_ACTIVITY = 'last_activity';

    private array $config;

    public function __construct(
        private readonly Session $session
    )
    {
        $this->config = $this->getDefaultConfig();
    }

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
            'session_lifetime' => 7200, // 2 hours
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

    // =============================================================================
    // PUBLIC API - Security Validation
    // =============================================================================

    public function validateSession(Request $request): array
    {
        $result = [
            'valid' => true,
            'violations' => [],
            'actions' => [],
        ];

        // 1. Check session expiry
        if ($this->isSessionExpired()) {
            $result['valid'] = false;
            $result['violations'][] = [
                'type' => 'session_expired',
                'message' => 'Session has expired'
            ];
            return $result;
        }

        // 2. Check fingerprint if enabled
        if ($this->config['enable_fingerprinting']) {
            $fingerprintValid = $this->validateFingerprint($request);
            if (!$fingerprintValid) {
                $result['valid'] = false;
                $result['violations'][] = [
                    'type' => 'fingerprint_mismatch',
                    'message' => 'Session fingerprint validation failed'
                ];
                $this->recordSecurityViolation();
                return $result;
            }
        }

        // 3. Check security lockout
        if ($this->isLockedOutDueToViolations()) {
            $result['valid'] = false;
            $result['violations'][] = [
                'type' => 'locked_out',
                'message' => 'Session locked due to security violations'
            ];
            return $result;
        }

        // 4. Check if regeneration needed
        if ($this->shouldRegenerateSession()) {
            $result['actions'][] = 'regenerate_session';
        }

        // 5. Update activity
        $this->updateActivity();

        return $result;
    }

    private function isSessionExpired(): bool
    {
        $lastActivity = $this->session->getFramework(self::LAST_ACTIVITY);
        if (!$lastActivity) {
            return true;
        }

        return (time() - $lastActivity) > $this->config['session_lifetime'];
    }

    private function validateFingerprint(Request $request): bool
    {
        $storedFingerprint = $this->session->getFramework(self::FINGERPRINT_KEY);
        if (!$storedFingerprint) {
            // No fingerprint stored - initialize it
            $fingerprint = $this->generateFingerprint($request);
            $this->session->setFramework(self::FINGERPRINT_KEY, $fingerprint);
            return true;
        }

        $currentFingerprint = $this->generateFingerprint($request);
        $isValid = $storedFingerprint === $currentFingerprint;

        if (!$isValid) {
            $this->logSecurityEvent('fingerprint_mismatch', [
                'stored' => $storedFingerprint,
                'current' => $currentFingerprint,
                'ip' => $request->ip(),
                'user_agent' => $request->getHeader('User-Agent'),
            ]);
        }

        return $isValid;
    }

    // =============================================================================
    // PUBLIC API - Login Attempts Management
    // =============================================================================

    private function generateFingerprint(Request $request): string
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

    private function logSecurityEvent(string $event, array $data = []): void
    {
        $logData = [
            'type' => 'security_event',
            'event' => $event,
            'data' => $data,
            'session_id' => $this->session->getId(),
            'timestamp' => time(),
        ];

        error_log('SessionSecurity: ' . json_encode($logData));
    }

    private function recordSecurityViolation(): void
    {
        $violations = $this->session->getFramework(self::SECURITY_VIOLATIONS, []);
        $violations[] = time();
        $this->session->setFramework(self::SECURITY_VIOLATIONS, $violations);

        $this->logSecurityEvent('security_violation_recorded', [
            'violations_count' => count($violations),
        ]);
    }

    private function isLockedOutDueToViolations(): bool
    {
        $violations = $this->session->getFramework(self::SECURITY_VIOLATIONS, []);

        // Clean old violations
        $cutoff = time() - $this->config['violation_lockout_time'];
        $violations = array_filter($violations, fn($time) => $time > $cutoff);

        // Update cleaned violations
        $this->session->setFramework(self::SECURITY_VIOLATIONS, $violations);

        return count($violations) >= $this->config['max_security_violations'];
    }

    // =============================================================================
    // PUBLIC API - Security Information
    // =============================================================================

    private function shouldRegenerateSession(): bool
    {
        $lastRegeneration = $this->session->getFramework(self::LAST_REGENERATION, 0);
        $interval = $this->config['min_regeneration_interval'];
        return (time() - $lastRegeneration) > $interval;
    }

    private function updateActivity(): void
    {
        $this->session->setFramework(self::LAST_ACTIVITY, time());
    }

    // =============================================================================
    // PRIVATE METHODS - Security Logic
    // =============================================================================

    public function initializeSession(Request $request): void
    {
        $now = time();

        // Set initial timestamps
        $this->session->setFramework(self::LAST_ACTIVITY, $now);
        $this->session->setFramework(self::LAST_REGENERATION, $now);

        // Initialize fingerprint if enabled
        if ($this->config['enable_fingerprinting']) {
            $fingerprint = $this->generateFingerprint($request);
            $this->session->setFramework(self::FINGERPRINT_KEY, $fingerprint);
        }

        // Clear any existing violations/attempts
        $this->clearSecurityViolations();
        $this->clearAllLoginAttempts();

        $this->logSecurityEvent('session_initialized', [
            'ip' => $request->ip(),
            'user_agent' => $request->getHeader('User-Agent'),
        ]);
    }

    private function clearSecurityViolations(): void
    {
        $this->session->removeFramework(self::SECURITY_VIOLATIONS);
    }

    private function clearAllLoginAttempts(): void
    {
        // Get all framework data and remove login attempt keys
        $frameworkData = $this->session->getFramework('', []);
        if (is_array($frameworkData)) {
            foreach (array_keys($frameworkData) as $key) {
                if (str_starts_with($key, self::LOGIN_ATTEMPTS_KEY)) {
                    $this->session->removeFramework($key);
                }
            }
        }
    }

    public function regenerateSession(string $reason = 'security'): bool
    {
        if (!$this->session->regenerate()) {
            return false;
        }

        $this->session->setFramework(self::LAST_REGENERATION, time());

        $this->logSecurityEvent('session_regenerated', [
            'reason' => $reason,
            'session_id' => $this->session->getId(),
        ]);

        return true;
    }

    public function recordLoginAttempt(string $identifier): int
    {
        $key = self::LOGIN_ATTEMPTS_KEY . '.' . $identifier;
        $attempts = $this->session->getFramework($key, []);

        $attempts[] = time();

        // Clean old attempts
        $window = $this->config['login_attempt_window'];
        $cutoff = time() - $window;
        $attempts = array_filter($attempts, fn($time) => $time > $cutoff);

        $this->session->setFramework($key, $attempts);

        $this->logSecurityEvent('login_attempt_recorded', [
            'identifier' => $identifier,
            'attempts_count' => count($attempts),
        ]);

        return count($attempts);
    }

    public function clearLoginAttempts(string $identifier): void
    {
        $key = self::LOGIN_ATTEMPTS_KEY . '.' . $identifier;
        $this->session->removeFramework($key);

        $this->logSecurityEvent('login_attempts_cleared', [
            'identifier' => $identifier,
        ]);
    }

    public function getSecurityStats(): array
    {
        return [
            'fingerprint_enabled' => $this->config['enable_fingerprinting'],
            'has_fingerprint' => $this->session->hasFramework(self::FINGERPRINT_KEY),
            'last_regeneration' => $this->session->getFramework(self::LAST_REGENERATION),
            'last_activity' => $this->session->getFramework(self::LAST_ACTIVITY),
            'security_violations' => count($this->session->getFramework(self::SECURITY_VIOLATIONS, [])),
            'locked_out' => $this->isLockedOutDueToViolations(),
            'session_expired' => $this->isSessionExpired(),
        ];
    }

    public function getLoginStats(string $identifier): array
    {
        $key = self::LOGIN_ATTEMPTS_KEY . '.' . $identifier;
        $attempts = $this->session->getFramework($key, []);

        $window = $this->config['login_attempt_window'];
        $cutoff = time() - $window;
        $attempts = array_filter($attempts, fn($time) => $time > $cutoff);

        return [
            'attempts_count' => count($attempts),
            'remaining_attempts' => $this->getRemainingLoginAttempts($identifier),
            'is_blocked' => $this->isLoginBlocked($identifier),
            'next_attempt_allowed_at' => $this->isLoginBlocked($identifier)
                ? max($attempts) + $window
                : time(),
        ];
    }

    public function getRemainingLoginAttempts(string $identifier): int
    {
        if ($this->isLoginBlocked($identifier)) {
            return 0;
        }

        $key = self::LOGIN_ATTEMPTS_KEY . '.' . $identifier;
        $attempts = $this->session->getFramework($key, []);

        $window = $this->config['login_attempt_window'];
        $cutoff = time() - $window;
        $attempts = array_filter($attempts, fn($time) => $time > $cutoff);

        return max(0, $this->config['max_login_attempts'] - count($attempts));
    }

    public function isLoginBlocked(string $identifier): bool
    {
        $key = self::LOGIN_ATTEMPTS_KEY . '.' . $identifier;
        $attempts = $this->session->getFramework($key, []);

        $window = $this->config['login_attempt_window'];
        $cutoff = time() - $window;
        $attempts = array_filter($attempts, fn($time) => $time > $cutoff);

        return count($attempts) >= $this->config['max_login_attempts'];
    }
}