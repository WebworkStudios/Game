<?php

/**
 * Optimized Session Manager
 * Secure session management with enhanced PHP 8.4 property hooks
 *
 * File: framework/Core/SessionManager.php
 * Directory: /framework/Core/
 */

declare(strict_types=1);

namespace Framework\Core;

class SessionManager implements SessionManagerInterface
{
    private const FLASH_KEY = '_flash';
    private const SYSTEM_KEYS = [self::FLASH_KEY, 'csrf_tokens', '_fingerprint', '_last_regenerate'];

    private array $config;
    private bool $started = false;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * User ID property with validation and automatic session start
     */
    public ?int $userId {
        get {
            $this->ensureStarted();
            return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        }
        set(?int $value) {
            $this->ensureStarted();
            if ($value === null) {
                unset($_SESSION['user_id']);
            } else {
                if ($value <= 0) {
                    throw new \InvalidArgumentException('User ID must be positive');
                }
                $_SESSION['user_id'] = $value;
                $this->updateLastActivity();
            }
        }
    }

    /**
     * Team ID property with validation
     */
    public ?int $teamId {
        get {
            $this->ensureStarted();
            return isset($_SESSION['team_id']) ? (int)$_SESSION['team_id'] : null;
        }
        set(?int $value) {
            $this->ensureStarted();
            if ($value === null) {
                unset($_SESSION['team_id']);
            } else {
                if ($value <= 0) {
                    throw new \InvalidArgumentException('Team ID must be positive');
                }
                $_SESSION['team_id'] = $value;
            }
        }
    }

    /**
     * Trainer name property with validation
     */
    public ?string $trainerName {
        get {
            $this->ensureStarted();
            return $_SESSION['trainer_name'] ?? null;
        }
        set(?string $value) {
            $this->ensureStarted();
            if ($value === null) {
                unset($_SESSION['trainer_name']);
            } else {
                $trimmed = trim($value);
                if (strlen($trimmed) < 3) {
                    throw new \InvalidArgumentException('Trainer name must be at least 3 characters');
                }
                $_SESSION['trainer_name'] = $trimmed;
            }
        }
    }

    /**
     * Premium status property
     */
    public bool $isPremium {
        get {
            $this->ensureStarted();
            return $_SESSION['is_premium'] ?? false;
        }
        set(bool $value) {
            $this->ensureStarted();
            $_SESSION['is_premium'] = $value;
        }
    }

    /**
     * Last activity timestamp with automatic updates
     */
    public int $lastActivity {
        get {
            $this->ensureStarted();
            return $_SESSION['last_activity'] ?? time();
        }
        set(int $value) {
            $this->ensureStarted();
            if ($value > time()) {
                throw new \InvalidArgumentException('Last activity cannot be in the future');
            }
            $_SESSION['last_activity'] = $value;
        }
    }

    /**
     * Session fingerprint (read-only)
     */
    public string $fingerprint {
        get {
            $this->ensureStarted();
            return $_SESSION['_fingerprint'] ?? $this->generateFingerprint();
        }
    }

    /**
     * Authentication status (computed property)
     */
    public bool $isAuthenticated {
        get => $this->userId !== null;
    }

    /**
     * Session age in seconds (computed property)
     */
    public int $sessionAge {
        get => time() - $this->lastActivity;
    }

    /**
     * Check if session is expired (computed property)
     */
    public bool $isExpired {
        get {
            if (!$this->isAuthenticated) {
                return false;
            }
            $maxInactiveTime = $this->config['max_inactive_time'] ?? 3600;
            return $this->sessionAge > $maxInactiveTime;
        }
    }

    /**
     * User data as array (computed property)
     */
    public array $userData {
        get => [
            'user_id' => $this->userId,
            'team_id' => $this->teamId,
            'trainer_name' => $this->trainerName,
            'is_premium' => $this->isPremium,
            'last_activity' => $this->lastActivity,
            'is_authenticated' => $this->isAuthenticated,
            'session_age' => $this->sessionAge,
            'is_expired' => $this->isExpired
        ];
    }

    /**
     * Start session if not already started
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $this->configureSession();

        if (!session_start()) {
            throw new \RuntimeException('Failed to start session');
        }

        $this->started = true;
        $this->initializeSession();
    }

    /**
     * Initialize session after start
     */
    private function initializeSession(): void
    {
        // Initialize flash storage if not exists
        if (!isset($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = [];
        }

        // Update last activity
        $this->updateLastActivity();

        // Security: Regenerate ID periodically and validate fingerprint
        $this->handleSessionSecurity();
    }

    /**
     * Update last activity timestamp
     */
    private function updateLastActivity(): void
    {
        $_SESSION['last_activity'] = time();
    }

    /**
     * Configure session settings
     */
    private function configureSession(): void
    {
        $sessionConfig = $this->config;

        // Session name
        if (isset($sessionConfig['name'])) {
            session_name($sessionConfig['name']);
        }

        // Session parameters
        session_set_cookie_params([
            'lifetime' => $sessionConfig['lifetime'] ?? 0,
            'path' => $sessionConfig['path'] ?? '/',
            'domain' => $sessionConfig['domain'] ?? '',
            'secure' => $sessionConfig['secure'] ?? false,
            'httponly' => $sessionConfig['httponly'] ?? true,
            'samesite' => $sessionConfig['samesite'] ?? 'Strict'
        ]);

        // Additional PHP ini settings
        ini_set('session.gc_maxlifetime', (string)($sessionConfig['gc_maxlifetime'] ?? 1440));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
    }

    /**
     * Handle session security measures
     */
    private function handleSessionSecurity(): void
    {
        // Regenerate session ID periodically (every 30 minutes)
        $regenerateInterval = $this->config['regenerate_interval'] ?? 1800;
        $lastRegenerate = $_SESSION['_last_regenerate'] ?? 0;

        if (time() - $lastRegenerate > $regenerateInterval) {
            $this->regenerateId();
            $_SESSION['_last_regenerate'] = time();
        }

        // Validate session fingerprint
        $this->validateSessionFingerprint();
    }

    /**
     * Validate session fingerprint for security
     */
    private function validateSessionFingerprint(): void
    {
        $currentFingerprint = $this->generateFingerprint();
        $sessionFingerprint = $_SESSION['_fingerprint'] ?? null;

        if ($sessionFingerprint === null) {
            $_SESSION['_fingerprint'] = $currentFingerprint;
        } elseif ($sessionFingerprint !== $currentFingerprint) {
            // Possible session hijacking - destroy session
            $this->destroy();
            throw new \RuntimeException('Session security validation failed');
        }
    }

    /**
     * Generate session fingerprint
     */
    private function generateFingerprint(): string
    {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            // Add remote address for additional security (be careful with proxies)
            $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Ensure session is started
     */
    private function ensureStarted(): void
    {
        if (!$this->isActive()) {
            $this->start();
        }
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Regenerate session ID for security
     */
    public function regenerateId(bool $deleteOldSession = true): void
    {
        $this->ensureStarted();

        if (!session_regenerate_id($deleteOldSession)) {
            throw new \RuntimeException('Failed to regenerate session ID');
        }
    }

    /**
     * Destroy session and all data
     */
    public function destroy(): void
    {
        if ($this->isActive()) {
            $_SESSION = [];

            // Delete session cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();
        }

        $this->started = false;
    }

    /**
     * Get session ID
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Set session name
     */
    public function setName(string $name): void
    {
        if ($this->isActive()) {
            throw new \RuntimeException('Cannot change session name after session has started');
        }
        session_name($name);
    }

    /**
     * Get session name
     */
    public function getName(): string
    {
        return session_name();
    }

    /**
     * Store flash message for next request
     */
    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    /**
     * Get and remove flash message
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();

        if (!isset($_SESSION[self::FLASH_KEY][$key])) {
            return $default;
        }

        $value = $_SESSION[self::FLASH_KEY][$key];
        unset($_SESSION[self::FLASH_KEY][$key]);

        return $value;
    }

    /**
     * Check if flash message exists
     */
    public function hasFlash(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[self::FLASH_KEY][$key]);
    }

    /**
     * Get all flash messages and clear them
     */
    public function getAllFlash(): array
    {
        $this->ensureStarted();

        $flash = $_SESSION[self::FLASH_KEY] ?? [];
        $_SESSION[self::FLASH_KEY] = [];

        return $flash;
    }

    /**
     * Set session value with validation
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();

        if (in_array($key, self::SYSTEM_KEYS)) {
            throw new \InvalidArgumentException("Cannot set system key: {$key}");
        }

        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session key with validation
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();

        if (in_array($key, self::SYSTEM_KEYS)) {
            throw new \InvalidArgumentException("Cannot remove system key: {$key}");
        }

        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data except system keys
     */
    public function clear(): void
    {
        $this->ensureStarted();

        $systemData = [];
        foreach (self::SYSTEM_KEYS as $systemKey) {
            if (isset($_SESSION[$systemKey])) {
                $systemData[$systemKey] = $_SESSION[$systemKey];
            }
        }

        $_SESSION = $systemData;
    }

    /**
     * Get all session data
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    /**
     * Log in user with validation
     */
    public function loginUser(int $userId, string $trainerName, bool $isPremium = false): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('User ID must be positive');
        }

        if (strlen(trim($trainerName)) < 3) {
            throw new \InvalidArgumentException('Trainer name must be at least 3 characters');
        }

        $this->regenerateId(); // Security: new session ID on login

        $this->userId = $userId;
        $this->trainerName = $trainerName;
        $this->isPremium = $isPremium;
        $this->updateLastActivity();
    }

    /**
     * Log out user
     */
    public function logoutUser(): void
    {
        $this->userId = null;
        $this->teamId = null;
        $this->trainerName = null;
        $this->isPremium = false;

        $this->regenerateId(); // Security: new session ID on logout
    }

    /**
     * Extend session activity
     */
    public function touch(): void
    {
        $this->updateLastActivity();
    }

    /**
     * Check if session has expired with custom timeout
     */
    public function isExpiredWithTimeout(int $maxInactiveTime): bool
    {
        if (!$this->isAuthenticated) {
            return false;
        }

        return $this->sessionAge > $maxInactiveTime;
    }

    /**
     * Validate session integrity
     */
    public function validateIntegrity(): bool
    {
        try {
            $this->ensureStarted();
            $this->validateSessionFingerprint();

            // Check if session is corrupted
            if (!is_array($_SESSION)) {
                return false;
            }

            // Validate required flash structure
            if (isset($_SESSION[self::FLASH_KEY]) && !is_array($_SESSION[self::FLASH_KEY])) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Clean expired flash messages (optional maintenance)
     */
    public function cleanExpiredFlash(): int
    {
        $this->ensureStarted();

        $cleaned = 0;
        $flash = $_SESSION[self::FLASH_KEY] ?? [];

        // This is a simple implementation - in a real app you might store timestamps
        // For now, we'll just clean empty values
        foreach ($flash as $key => $value) {
            if ($value === null || $value === '') {
                unset($_SESSION[self::FLASH_KEY][$key]);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}