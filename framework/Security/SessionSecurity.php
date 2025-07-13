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
    public function validateSession(Request $request): SecurityValidationResult
    {
        $result = new SecurityValidationResult();

        // Check if session is locked due to violations
        if ($this->isSessionLocked()) {
            $result->setValid(false);
            $result->addViolation('session_locked', 'Session is locked due to security violations');
            return $result;
        }

        // Check fingerprint if enabled
        if ($this->config['enable_fingerprinting']) {
            $fingerprintResult = $this->validateFingerprint($request);
            if (!$fingerprintResult->isValid()) {
                $result->merge($fingerprintResult);
            }
        }

        // Check session expiry
        if ($this->session->isExpired()) {
            $result->setValid(false);
            $result->addViolation('session_expired', 'Session has expired');
        }

        // Check if regeneration is needed
        if ($this->shouldRegenerateSession()) {
            $result->setRegenerationRequired(true);
        }

        return $result;
    }

    /**
     * Check if session is locked
     */
    public function isSessionLocked(): bool
    {
        $lockedUntil = $this->session->getFramework('locked_until');

        if (!$lockedUntil) {
            return false;
        }

        if (time() >= $lockedUntil) {
            // Lock expired, clear it
            $this->session->removeFramework('locked_until');
            return false;
        }

        return true;
    }

    /**
     * Validate session fingerprint
     */
    private function validateFingerprint(Request $request): SecurityValidationResult
    {
        $result = new SecurityValidationResult();
        $storedFingerprint = $this->session->getFramework(self::FINGERPRINT_KEY);

        if (!$storedFingerprint) {
            // No fingerprint stored, initialize it
            $this->initializeSecurity($request);
            return $result;
        }

        $currentFingerprint = $this->generateFingerprint($request);

        if ($storedFingerprint !== $currentFingerprint) {
            $result->setValid(false);
            $result->addViolation('fingerprint_mismatch', 'Session fingerprint mismatch detected');

            // Log potential hijacking attempt
            $this->logSecurityEvent('fingerprint_mismatch', [
                'stored' => $storedFingerprint,
                'current' => $currentFingerprint,
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            $this->recordSecurityViolation();
        }

        return $result;
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
            $components[] = $request->header('User-Agent') ?? '';
        }

        if ($this->config['fingerprint_components']['accept_language']) {
            $components[] = $request->header('Accept-Language') ?? '';
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
            $sessionData = $this->session->getFramework('');
            if (is_array($sessionData)) {
                foreach (array_keys($sessionData) as $key) {
                    if (str_starts_with($key, self::LOGIN_ATTEMPTS_KEY)) {
                        $this->session->removeFramework($key);
                    }
                }
            }
        }
    }

    /**
     * Clear security violations
     */
    public function clearSecurityViolations(): void
    {
        $this->session->removeFramework(self::SECURITY_VIOLATIONS);
        $this->session->removeFramework('locked_until');
    }

    /**
     * Log security events
     */
    private function logSecurityEvent(string $event, array $context = []): void
    {
        $logData = [
            'event' => $event,
            'time' => time(),
            'session_id' => $this->session->getId(),
            'context' => $context,
        ];

        // Log to file or external service
        error_log('Security Event: ' . json_encode($logData));
    }

    /**
     * Security violation tracking
     */
    public function recordSecurityViolation(string $type = 'general'): void
    {
        $violations = $this->getSecurityViolations();
        $violations[] = [
            'type' => $type,
            'time' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];

        $this->session->setFramework(self::SECURITY_VIOLATIONS, $violations);

        // Check if session should be locked
        if (count($violations) >= $this->config['max_security_violations']) {
            $this->lockSession();
        }
    }

    /**
     * Get security violations
     */
    public function getSecurityViolations(): array
    {
        return $this->session->getFramework(self::SECURITY_VIOLATIONS, []);
    }

    /**
     * Lock session due to security violations
     */
    public function lockSession(): void
    {
        $lockUntil = time() + $this->config['violation_lockout_time'];
        $this->session->setFramework('locked_until', $lockUntil);

        $this->logSecurityEvent('session_locked', [
            'locked_until' => $lockUntil,
            'violations' => $this->getSecurityViolations(),
        ]);
    }

    /**
     * Check if session should be regenerated
     */
    public function shouldRegenerateSession(): bool
    {
        $lastRegeneration = $this->session->getFramework(self::LAST_REGENERATION);

        if (!$lastRegeneration) {
            return true;
        }

        return (time() - $lastRegeneration) >= $this->config['min_regeneration_interval'];
    }

    /**
     * Login attempt tracking
     */
    public function recordLoginAttempt(string $identifier, bool $successful = false): bool
    {
        $attempts = $this->getLoginAttempts($identifier);
        $now = time();

        // Clean old attempts outside the window
        $attempts = array_filter($attempts, function ($attempt) use ($now) {
            return ($now - $attempt['time']) <= $this->config['login_attempt_window'];
        });

        // Add new attempt
        $attempts[] = [
            'time' => $now,
            'successful' => $successful,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];

        $this->session->setFramework(self::LOGIN_ATTEMPTS_KEY . '.' . $identifier, $attempts);

        return $this->canAttemptLogin($identifier);
    }

    /**
     * Get login attempts for identifier
     */
    public function getLoginAttempts(string $identifier): array
    {
        return $this->session->getFramework(self::LOGIN_ATTEMPTS_KEY . '.' . $identifier, []);
    }

    /**
     * Check if login attempts are within limits
     */
    public function canAttemptLogin(string $identifier): bool
    {
        $attempts = $this->getLoginAttempts($identifier);
        $failedAttempts = array_filter($attempts, function ($attempt) {
            return !$attempt['successful'];
        });

        return count($failedAttempts) < $this->config['max_login_attempts'];
    }

    /**
     * Handle successful login
     */
    public function handleSuccessfulLogin(string $identifier, Request $request): void
    {
        // Clear failed login attempts
        $this->clearLoginAttempts($identifier);

        // Clear security violations
        $this->clearSecurityViolations();

        // Regenerate session
        $this->regenerateSession($request);

        // Re-initialize security
        $this->initializeSecurity($request);

        $this->logSecurityEvent('successful_login', [
            'identifier' => $identifier,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Regenerate session with security updates
     */
    public function regenerateSession(Request $request, bool $deleteOld = true): bool
    {
        if (!$this->session->regenerate($deleteOld)) {
            return false;
        }

        // Update security data
        if ($this->config['enable_fingerprinting']) {
            $fingerprint = $this->generateFingerprint($request);
            $this->session->setFramework(self::FINGERPRINT_KEY, $fingerprint);
        }

        $this->session->setFramework(self::LAST_REGENERATION, time());

        return true;
    }

    /**
     * Handle logout
     */
    public function handleLogout(Request $request): void
    {
        $this->logSecurityEvent('logout', [
            'ip' => $request->ip(),
        ]);

        // Clear all security data
        $this->clearLoginAttempts();
        $this->clearSecurityViolations();
    }

    /**
     * Get security status
     */
    public function getSecurityStatus(): array
    {
        return [
            'fingerprinting_enabled' => $this->config['enable_fingerprinting'],
            'fingerprint_set' => $this->session->hasFramework(self::FINGERPRINT_KEY),
            'last_regeneration' => $this->session->getFramework(self::LAST_REGENERATION),
            'should_regenerate' => $this->shouldRegenerateSession(),
            'is_locked' => $this->isSessionLocked(),
            'security_violations' => count($this->getSecurityViolations()),
            'config' => $this->config,
        ];
    }

    /**
     * Force session cleanup and security reset
     */
    public function forceSecurityReset(Request $request): void
    {
        $this->clearLoginAttempts();
        $this->clearSecurityViolations();
        $this->regenerateSession($request);
        $this->initializeSecurity($request);
    }
}

/**
 * Security validation result
 */
class SecurityValidationResult
{
    private bool $valid = true;
    private bool $regenerationRequired = false;
    private array $violations = [];

    public function addViolation(string $type, string $message): void
    {
        $this->violations[] = [
            'type' => $type,
            'message' => $message,
            'time' => time(),
        ];
        $this->valid = false;
    }

    public function merge(SecurityValidationResult $other): void
    {
        if (!$other->isValid()) {
            $this->valid = false;
        }

        if ($other->isRegenerationRequired()) {
            $this->regenerationRequired = true;
        }

        $this->violations = array_merge($this->violations, $other->getViolations());
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    public function isRegenerationRequired(): bool
    {
        return $this->regenerationRequired;
    }

    public function setRegenerationRequired(bool $required): void
    {
        $this->regenerationRequired = $required;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }
}