<?php


/**
 * Session Manager
 * Secure session management with PHP 8.4 property hooks
 *
 * File: framework/Core/SessionManager.php
 * Directory: /framework/Core/
 */

declare(strict_types=1);

namespace Framework\Core;

class SessionManager implements SessionManagerInterface
{
    private const FLASH_KEY = '_flash';
    private const SYSTEM_KEYS = [self::FLASH_KEY, 'csrf_tokens'];

    private array $config;
    private bool $started = false;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * User ID property with type safety
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
                $_SESSION['user_id'] = $value;
            }
        }
    }

    /**
     * Team ID property (for future use)
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
                $_SESSION['team_id'] = $value;
            }
        }
    }

    /**
     * Premium status property (for future use)
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
     * Last activity timestamp
     */
    public int $lastActivity {
        get {
            $this->ensureStarted();
            return $_SESSION['last_activity'] ?? time();
        }
        set(int $value) {
            $this->ensureStarted();
            $_SESSION['last_activity'] = $value;
        }
    }

    /**
     * Start session if not already started
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Configure session before starting
        $this->configureSession();

        if (!session_start()) {
            throw new \RuntimeException('Failed to start session');
        }

        $this->started = true;

        // Initialize flash storage if not exists
        if (!isset($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = [];
        }

        // Update last activity
        $this->lastActivity = time();

        // Security: Regenerate ID periodically
        $this->handleSessionSecurity();
    }

    /**
     * Configure session settings
     */
    private function configureSession(): void
    {
        $sessionConfig = $this->config['session'] ?? [];

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
        $regenerateInterval = 1800; // 30 minutes
        $lastRegenerate = $_SESSION['_last_regenerate'] ?? 0;

        if (time() - $lastRegenerate > $regenerateInterval) {
            $this->regenerateId();
            $_SESSION['_last_regenerate'] = time();
        }

        // Check for session hijacking
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
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

        return hash('sha256', $userAgent . $acceptLanguage . $acceptEncoding);
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
     * Set session value
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
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
     * Remove session key
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();
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
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Log in user
     */
    public function loginUser(int $userId, string $trainerName, bool $isPremium = false): void
    {
        $this->regenerateId(); // Security: new session ID on login

        $this->userId = $userId;
        $this->isPremium = $isPremium;
        $this->lastActivity = time();
    }

    /**
     * Log out user
     */
    public function logoutUser(): void
    {
        $this->userId = null;
        $this->teamId = null;
        $this->isPremium = false;

        $this->regenerateId(); // Security: new session ID on logout
    }

    /**
     * Get user session data
     */
    public function getUserData(): array
    {
        return [
            'user_id' => $this->userId,
            'team_id' => $this->teamId,
            'is_premium' => $this->isPremium,
            'last_activity' => $this->lastActivity,
            'is_authenticated' => $this->isAuthenticated()
        ];
    }

    /**
     * Check if session has expired
     */
    public function isExpired(int $maxInactiveTime = 3600): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        return (time() - $this->lastActivity) > $maxInactiveTime;
    }

    /**
     * Extend session activity
     */
    public function touch(): void
    {
        $this->lastActivity = time();
    }
}