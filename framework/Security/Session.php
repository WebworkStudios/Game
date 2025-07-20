<?php

declare(strict_types=1);

namespace Framework\Security;

use RuntimeException;

/**
 * Session - Pure Data Layer für Session Management
 *
 * Verantwortlichkeiten:
 * - Session Start/Stop/Destroy
 * - Daten Get/Set (User-Daten + Framework-interne Daten)
 * - Flash Messages
 * - Session-Status
 */
class Session
{
    private const string FRAMEWORK_NAMESPACE = '_framework';
    private const string FLASH_NAMESPACE = '_flash';

    private bool $started = false;
    private array $config;
    private ?string $sessionId = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->setupSessionConfiguration();
    }

    private function getDefaultConfig(): array
    {
        return [
            'lifetime' => 7200,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
            'gc_maxlifetime' => 7200,
            'gc_probability' => 1,
            'gc_divisor' => 100,
            'save_path' => null,
        ];
    }

    private function setupSessionConfiguration(): void
    {
        $savePath = $this->config['save_path'] ?? $this->getDefaultSavePath();
        if (!is_dir($savePath)) {
            mkdir($savePath, 0755, true);
        }

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
        ini_set('session.auto_start', '0');
    }

    private function getDefaultSavePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/sessions';
    }

    // =============================================================================
    // PUBLIC API - Session Lifecycle
    // =============================================================================

    public function regenerate(bool $deleteOldSession = true): bool
    {
        $this->start();

        if (!session_regenerate_id($deleteOldSession)) {
            return false;
        }

        $this->sessionId = session_id();
        return true;
    }

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
        return true;
    }

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

    // =============================================================================
    // PUBLIC API - Data Management
    // =============================================================================

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function all(): array
    {
        $this->start();
        return $_SESSION;
    }

    public function clear(): void
    {
        $this->start();
        $_SESSION = [];
    }

    // =============================================================================
    // PUBLIC API - Framework Internal Data
    // =============================================================================

    public function flash(string $key, mixed $value): void
    {
        $this->setFramework(self::FLASH_NAMESPACE . '.' . $key, $value);
    }

    public function setFramework(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[self::FRAMEWORK_NAMESPACE][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->getFramework(self::FLASH_NAMESPACE . '.' . $key, $default);
        $this->removeFramework(self::FLASH_NAMESPACE . '.' . $key);
        return $value;
    }

    public function getFramework(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[self::FRAMEWORK_NAMESPACE][$key] ?? $default;
    }

    // =============================================================================
    // PUBLIC API - Flash Messages
    // =============================================================================

    public function removeFramework(string $key): void
    {
        $this->start();
        unset($_SESSION[self::FRAMEWORK_NAMESPACE][$key]);
    }

    public function getFlashBag(): array
    {
        $flashData = $this->getFramework(self::FLASH_NAMESPACE, []);
        $this->removeFramework(self::FLASH_NAMESPACE);
        return $flashData;
    }

    public function hasFlash(string $key): bool
    {
        return $this->hasFramework(self::FLASH_NAMESPACE . '.' . $key);
    }

    public function hasFramework(string $key): bool
    {
        $this->start();
        return isset($_SESSION[self::FRAMEWORK_NAMESPACE][$key]);
    }

    // =============================================================================
    // PUBLIC API - Status & Information
    // =============================================================================

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function getId(): ?string
    {
        $this->start();
        return $this->sessionId;
    }

    public function gc(): int
    {
        $this->start();
        return (int)session_gc();
    }

}