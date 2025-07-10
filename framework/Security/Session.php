<?php
declare(strict_types=1);

namespace Framework\Security;

use RuntimeException;

/**
 * Session Manager - Zentrale Session-Verwaltung mit Namespaces und Security-Features
 */
class Session
{
    private const string FRAMEWORK_NAMESPACE = '_framework';
    private const string FLASH_NAMESPACE = '_flash';
    private const string CSRF_TOKEN_KEY = 'csrf_token';

    private bool $started = false;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->configureSession();
    }

    /**
     * Standard-Konfiguration
     */
    private function getDefaultConfig(): array
    {
        return [
            'lifetime' => 7200, // 2 Stunden
            'path' => '/',
            'domain' => '',
            'secure' => false, // In Production auf true setzen
            'httponly' => true,
            'samesite' => 'Lax',
            'gc_maxlifetime' => 7200,
            'gc_probability' => 1,
            'gc_divisor' => 100,
        ];
    }

    /**
     * Konfiguriert Session-Parameter
     */
    private function configureSession(): void
    {
        // Nur konfigurieren wenn Session noch nicht gestartet
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        // Cookie-Parameter setzen
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'],
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => $this->config['httponly'],
            'samesite' => $this->config['samesite'],
        ]);

        // Garbage Collection
        ini_set('session.gc_maxlifetime', (string)$this->config['gc_maxlifetime']);
        ini_set('session.gc_probability', (string)$this->config['gc_probability']);
        ini_set('session.gc_divisor', (string)$this->config['gc_divisor']);

        // Security-Einstellungen
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_strict_mode', '1');
    }

    /**
     * Leert die komplette Session
     */
    public function clear(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
    }

    /**
     * Stellt sicher dass Session gestartet ist
     */
    private function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
    }

    /**
     * Startet die Session
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return true;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException("Cannot start session: headers already sent in {$file} on line {$line}");
        }

        $result = session_start();

        if ($result) {
            $this->started = true;
            $this->initializeSession();
        }

        return $result;
    }

    /**
     * Initialisiert Session-Struktur
     */
    private function initializeSession(): void
    {
        // Framework-Namespace initialisieren
        if (!isset($_SESSION[self::FRAMEWORK_NAMESPACE])) {
            $_SESSION[self::FRAMEWORK_NAMESPACE] = [];
        }

        // Flash-Namespace initialisieren
        if (!isset($_SESSION[self::FLASH_NAMESPACE])) {
            $_SESSION[self::FLASH_NAMESPACE] = [];
        }
    }

    /**
     * Zerstört die Session komplett
     */
    public function destroy(): bool
    {
        $this->ensureStarted();

        // Session-Cookie löschen
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

        return $result;
    }

    /**
     * Regeneriert die Session-ID (Security)
     */
    public function regenerate(bool $deleteOld = true): bool
    {
        $this->ensureStarted();
        return session_regenerate_id($deleteOld);
    }

    /**
     * Holt die aktuelle Session-ID
     */
    public function getId(): string
    {
        $this->ensureStarted();
        return session_id();
    }

    /**
     * Setzt die Session-ID
     */
    public function setId(string $id): void
    {
        if ($this->started) {
            throw new RuntimeException('Cannot change session ID after session has been started');
        }

        session_id($id);
    }

    /**
     * Framework-interne Werte setzen
     */
    public function setFramework(string $key, mixed $value): void
    {
        $this->set(self::FRAMEWORK_NAMESPACE . '.' . $key, $value);
    }

    /**
     * Setzt einen Wert in der Session
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();

        if (str_contains($key, '.')) {
            $this->setNested($key, $value);
        } else {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Setzt verschachtelten Wert mit Dot-Notation
     */
    private function setNested(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$_SESSION;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    /**
     * Framework-interne Werte holen
     */
    public function getFramework(string $key, mixed $default = null): mixed
    {
        return $this->get(self::FRAMEWORK_NAMESPACE . '.' . $key, $default);
    }

    /**
     * Holt einen Wert aus der Session
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();

        if (str_contains($key, '.')) {
            return $this->getNested($key, $default);
        }

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Holt verschachtelten Wert mit Dot-Notation
     */
    private function getNested(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $current = $_SESSION;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return $default;
            }
            $current = $current[$k];
        }

        return $current;
    }

    /**
     * Flash-Message holen und entfernen
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->get(self::FLASH_NAMESPACE . '.' . $key, $default);
        $this->remove(self::FLASH_NAMESPACE . '.' . $key);
        return $value;
    }

    /**
     * Entfernt einen Wert aus der Session
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();

        if (str_contains($key, '.')) {
            $this->removeNested($key);
        } else {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Entfernt verschachtelten Wert mit Dot-Notation
     */
    private function removeNested(string $key): void
    {
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        $current = &$_SESSION;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                return; // Pfad existiert nicht
            }
            $current = &$current[$k];
        }

        unset($current[$lastKey]);
    }

    /**
     * Alle Flash-Messages holen und entfernen
     */
    public function getAllFlash(): array
    {
        $flash = $this->get(self::FLASH_NAMESPACE, []);
        $this->remove(self::FLASH_NAMESPACE);
        return $flash;
    }

    /**
     * Prüft ob Flash-Message existiert
     */
    public function hasFlash(string $key): bool
    {
        return $this->has(self::FLASH_NAMESPACE . '.' . $key);
    }

    /**
     * Prüft ob ein Key existiert
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();

        if (str_contains($key, '.')) {
            return $this->hasNested($key);
        }

        return isset($_SESSION[$key]);
    }

    /**
     * Prüft verschachtelten Key mit Dot-Notation
     */
    private function hasNested(string $key): bool
    {
        $keys = explode('.', $key);
        $current = $_SESSION;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return false;
            }
            $current = $current[$k];
        }

        return true;
    }

    /**
     * Success Flash-Message (Convenience)
     */
    public function flashSuccess(string $message): void
    {
        $this->flash('success', $message);
    }

    /**
     * Flash-Message setzen (nur für nächsten Request verfügbar)
     */
    public function flash(string $key, mixed $value): void
    {
        $this->set(self::FLASH_NAMESPACE . '.' . $key, $value);
    }

    /**
     * Error Flash-Message (Convenience)
     */
    public function flashError(string $message): void
    {
        $this->flash('error', $message);
    }

    /**
     * Warning Flash-Message (Convenience)
     */
    public function flashWarning(string $message): void
    {
        $this->flash('warning', $message);
    }

    /**
     * Info Flash-Message (Convenience)
     */
    public function flashInfo(string $message): void
    {
        $this->flash('info', $message);
    }

    /**
     * Holt Session-Status
     */
    public function getStatus(): array
    {
        return [
            'started' => $this->started,
            'id' => $this->started ? session_id() : null,
            'status' => session_status(),
            'name' => session_name(),
            'save_path' => session_save_path(),
        ];
    }

    /**
     * Prüft ob Session gestartet ist
     */
    public function isStarted(): bool
    {
        return $this->started;
    }
}