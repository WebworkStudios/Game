<?php


declare(strict_types=1);

namespace Framework\Security;

use RuntimeException;

/**
 * Session - Secure Session Management with Lazy Loading
 * Handles file-based session storage with security features
 */
class Session
{
    private const string FRAMEWORK_NAMESPACE = '_framework';
    private const string FLASH_NAMESPACE = '_flash';
    private const string FINGERPRINT_KEY = '_fingerprint';
    private const string LAST_ACTIVITY = '_last_activity';
    private const string REGENERATION_TIME = '_regeneration_time';

    private bool $started = false;
    private array $config;
    private array $flashData = [];
    private ?string $sessionId = null;
    private array $data = [];
    private bool $dataLoaded = false;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->setupSessionConfiguration();
    }

    /**
     * Default session configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'lifetime' => 7200, // 2 hours
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
            'gc_maxlifetime' => 7200,
            'gc_probability' => 1,
            'gc_divisor' => 100,
            'save_path' => null, // Will use storage/sessions/
        ];
    }

    /**
     * Configure PHP session settings
     */
    private function setupSessionConfiguration(): void
    {
        // Set save path
        $savePath = $this->config['save_path'] ?? $this->getDefaultSavePath();
        if (!is_dir($savePath)) {
            mkdir($savePath, 0755, true);
        }

        // Configure session parameters
        ini_set('session.cookie_lifetime', (string)$this->config['lifetime']);
        ini_set('session.cookie_path', $this->config['path']);
        ini_set('session.cookie_domain', $this->config['domain']);
        ini_set('session.cookie_secure', $this->config['secure'] ? '1' : '0');
        ini_set('session.cookie_httponly', $this->config['httponly'] ? '1' : '0');
        ini_set('session.cookie_samesite', $this->config['samesite']);
        ini_set('session.gc_maxlifetime', (string)$this->config['gc_maxlifetime']);
        ini_set('session.gc_probability', (string)$this->config['gc_probability']);
        ini_set('session.gc_divisor', (string)$this->config['gc_divisor']);
        ini_set('session.save_path', $savePath);

        // Prevent session auto-start
        ini_set('session.auto_start', '0');
    }

    /**
     * Get default session save path
     */
    private function getDefaultSavePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/sessions';
    }

    /**
     * Check if session is started
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Get session ID
     */
    public function getId(): ?string
    {
        $this->start();
        return $this->sessionId;
    }

    /**
     * Lazy start session
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            $this->sessionId = session_id();
            return true;
        }

        if (!session_start()) {
            throw new RuntimeException('Failed to start session');
        }

        $this->started = true;
        $this->sessionId = session_id();

        // Initialize session if new
        if (!$this->has(self::LAST_ACTIVITY)) {
            $this->initializeNewSession();
        }

        $this->updateLastActivity();
        return true;
    }

    /**
     * Check if session key exists
     */
    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    /**
     * Initialize new session with security data
     */
    private function initializeNewSession(): void
    {
        $this->setFramework(self::LAST_ACTIVITY, time());
        $this->setFramework(self::REGENERATION_TIME, time());
    }

    /**
     * Framework-internal session data management
     */
    public function setFramework(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[self::FRAMEWORK_NAMESPACE][$key] = $value;
    }

    /**
     * Update last activity timestamp
     */
    private function updateLastActivity(): void
    {
        $this->setFramework(self::LAST_ACTIVITY, time());
    }

    /**
     * Regenerate session ID for security
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        $this->start();

        if (!session_regenerate_id($deleteOldSession)) {
            return false;
        }

        $this->sessionId = session_id();
        $this->setFramework(self::REGENERATION_TIME, time());
        return true;
    }

    /**
     * Set session value
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Remove session key
     */
    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Get all session data
     */
    public function all(): array
    {
        $this->start();
        return $_SESSION;
    }

    /**
     * Clear all session data
     */
    public function clear(): void
    {
        $this->start();
        $_SESSION = [];
    }

    public function flashSuccess(string $message): void
    {
        $this->flash('success', $message);
    }

    /**
     * Flash messages for one-time display
     */
    public function flash(string $key, mixed $value): void
    {
        $this->setFramework(self::FLASH_NAMESPACE . '.' . $key, $value);
    }

    public function flashError(string $message): void
    {
        $this->flash('error', $message);
    }

    public function flashWarning(string $message): void
    {
        $this->flash('warning', $message);
    }

    public function flashInfo(string $message): void
    {
        $this->flash('info', $message);
    }

    /**
     * Get flash message and remove it
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $flashKey = self::FLASH_NAMESPACE . '.' . $key;
        $value = $this->getFramework($flashKey, $default);
        $this->removeFramework($flashKey);
        return $value;
    }

    public function getFramework(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[self::FRAMEWORK_NAMESPACE][$key] ?? $default;
    }

    public function removeFramework(string $key): void
    {
        $this->start();
        unset($_SESSION[self::FRAMEWORK_NAMESPACE][$key]);
    }

    /**
     * Get all flash messages and clear them
     */
    public function getFlashBag(): array
    {
        $this->start();
        $flashData = $_SESSION[self::FRAMEWORK_NAMESPACE][self::FLASH_NAMESPACE] ?? [];
        unset($_SESSION[self::FRAMEWORK_NAMESPACE][self::FLASH_NAMESPACE]);
        return $flashData;
    }

    /**
     * Check if flash message exists
     */
    public function hasFlash(string $key): bool
    {
        return $this->hasFramework(self::FLASH_NAMESPACE . '.' . $key);
    }

    public function hasFramework(string $key): bool
    {
        $this->start();
        return isset($_SESSION[self::FRAMEWORK_NAMESPACE][$key]);
    }

    /**
     * Session security methods
     */
    public function setFingerprint(string $fingerprint): void
    {
        $this->setFramework(self::FINGERPRINT_KEY, $fingerprint);
    }

    /**
     * Destroy session completely
     */
    public function destroy(): bool
    {
        if (!$this->started) {
            return true;
        }

        $_SESSION = [];

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

        $result = session_destroy();
        $this->started = false;
        $this->sessionId = null;

        return $result;
    }

    /**
     * Get session configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get session status information
     */
    public function getStatus(): array
    {
        return [
            'started' => $this->started,
            'id' => $this->sessionId,
            'status' => session_status(),
            'last_activity' => $this->getLastActivity(),
            'regeneration_time' => $this->getRegenerationTime(),
            'expired' => $this->isExpired(),
            'fingerprint_set' => $this->getFingerprint() !== null,
        ];
    }

    public function getLastActivity(): ?int
    {
        return $this->getFramework(self::LAST_ACTIVITY);
    }

    public function getRegenerationTime(): ?int
    {
        return $this->getFramework(self::REGENERATION_TIME);
    }

    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        $lastActivity = $this->getLastActivity();
        if (!$lastActivity) {
            return true;
        }

        return (time() - $lastActivity) > $this->config['lifetime'];
    }

    public function getFingerprint(): ?string
    {
        return $this->getFramework(self::FINGERPRINT_KEY);
    }

    /**
     * Manual garbage collection
     */
    public function gc(): int
    {
        $this->start();
        return (int)session_gc();
    }

    /**
     * Get session data size
     */
    public function getDataSize(): int
    {
        $this->start();
        return strlen(serialize($_SESSION));
    }
}