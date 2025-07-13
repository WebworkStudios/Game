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
     * Prüft ob Session gestartet ist
     */
    public function isStarted(): bool
    {
        return $this->started;
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
     * Leert die komplette Session
     */
    public function clear(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
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
     * Entfernt Framework-interne Werte
     */
    public function removeFramework(string $key): void
    {
        $this->remove(self::FRAMEWORK_NAMESPACE . '.' . $key);
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
        $current = &$_SESSION;
        $lastKey = array_pop($keys);

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                return; // Pfad existiert nicht
            }
            $current = &$current[$k];
        }

        unset($current[$lastKey]);
    }

    /**
     * Holt alle Flash-Messages und entfernt sie
     */
    public function getAllFlash(): array
    {
        $this->ensureStarted();
        $flash = $_SESSION[self::FLASH_NAMESPACE] ?? [];
        $_SESSION[self::FLASH_NAMESPACE] = [];
        return $flash;
    }

    /**
     * Prüft ob Flash-Message existiert
     */
    public function hasFlash(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[self::FLASH_NAMESPACE][$key]);
    }

    /**
     * Setzt Flash-Success-Message
     */
    public function flashSuccess(string $message): void
    {
        $this->flash('success', $message);
    }

    /**
     * Setzt eine Flash-Message
     */
    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[self::FLASH_NAMESPACE][$key] = $value;
    }

    /**
     * Setzt Flash-Error-Message
     */
    public function flashError(string $message): void
    {
        $this->flash('error', $message);
    }

    /**
     * Setzt Flash-Info-Message
     */
    public function flashInfo(string $message): void
    {
        $this->flash('info', $message);
    }

    /**
     * Setzt Flash-Warning-Message
     */
    public function flashWarning(string $message): void
    {
        $this->flash('warning', $message);
    }

    /**
     * Holt Flash-Success-Message
     */
    public function getFlashSuccess(mixed $default = null): mixed
    {
        return $this->getFlash('success', $default);
    }

    /**
     * Holt eine Flash-Message und entfernt sie
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        $value = $_SESSION[self::FLASH_NAMESPACE][$key] ?? $default;
        unset($_SESSION[self::FLASH_NAMESPACE][$key]);
        return $value;
    }

    /**
     * Holt Flash-Error-Message
     */
    public function getFlashError(mixed $default = null): mixed
    {
        return $this->getFlash('error', $default);
    }

    /**
     * Holt Flash-Info-Message
     */
    public function getFlashInfo(mixed $default = null): mixed
    {
        return $this->getFlash('info', $default);
    }

    /**
     * Holt Flash-Warning-Message
     */
    public function getFlashWarning(mixed $default = null): mixed
    {
        return $this->getFlash('warning', $default);
    }

    /**
     * Setzt mehrere Werte auf einmal
     */
    public function put(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Holt mehrere Werte auf einmal
     */
    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if ($this->has($key)) {
                $result[$key] = $this->get($key);
            }
        }
        return $result;
    }

    /**
     * Prüft ob ein Key in der Session existiert
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();

        if (str_contains($key, '.')) {
            return $this->getNested($key) !== null;
        }

        return isset($_SESSION[$key]);
    }

    /**
     * Holt verschachtelten Wert mit Dot-Notation
     */
    private function getNested(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $current = $_SESSION;

        foreach ($keys as $k) {
            if (!is_array($current) || !isset($current[$k])) {
                return $default;
            }
            $current = $current[$k];
        }

        return $current;
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
     * Holt alle Werte außer den angegebenen
     */
    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    /**
     * Holt alle Session-Daten
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    /**
     * Atomische Operation: Pull (get + remove)
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    /**
     * Dekrementiert einen numerischen Wert
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    /**
     * Inkrementiert einen numerischen Wert
     */
    public function increment(string $key, int $value = 1): int
    {
        $current = (int)$this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new);
        return $new;
    }

    /**
     * Session-Token für CSRF-Schutz
     */
    public function token(): string
    {
        if (!$this->hasFramework(self::CSRF_TOKEN_KEY)) {
            $this->regenerateToken();
        }

        return $this->getFramework(self::CSRF_TOKEN_KEY);
    }

    /**
     * Prüft ob Framework-Wert existiert
     */
    public function hasFramework(string $key): bool
    {
        return $this->has(self::FRAMEWORK_NAMESPACE . '.' . $key);
    }

    /**
     * Regeneriert Session-Token
     */
    public function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->setFramework(self::CSRF_TOKEN_KEY, $token);
        return $token;
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
     * Validiert Session-Integrität
     */
    public function validate(): bool
    {
        if (!$this->started) {
            return false;
        }

        // Prüfe ob required Namespaces existieren
        if (!isset($_SESSION[self::FRAMEWORK_NAMESPACE]) ||
            !isset($_SESSION[self::FLASH_NAMESPACE])) {
            $this->initializeSession();
        }

        return true;
    }

    /**
     * Session-Cleanup für Development/Testing
     */
    public function cleanup(): void
    {
        $this->ensureStarted();

        // Entferne leere Arrays
        $_SESSION = array_filter($_SESSION, function ($value) {
            return !is_array($value) || !empty($value);
        });

        // Re-initialisiere Namespaces falls gelöscht
        $this->initializeSession();
    }

    /**
     * Exportiert Session-Daten (ohne sensible Informationen)
     */
    public function export(bool $includeSensitive = false): array
    {
        $this->ensureStarted();
        $data = $_SESSION;

        if (!$includeSensitive) {
            // Entferne Framework-interne Daten
            unset($data[self::FRAMEWORK_NAMESPACE]);

            // Entferne sensible Keys
            $sensitiveKeys = ['password', 'token', 'secret', 'key'];
            foreach ($sensitiveKeys as $sensitive) {
                unset($data[$sensitive]);
            }
        }

        return $data;
    }

    /**
     * Magic method für String-Konvertierung
     */
    public function __toString(): string
    {
        return $this->debug();
    }

    /**
     * Debug-Information als String
     */
    public function debug(): string
    {
        $stats = $this->getStats();
        $size = $this->getSize();

        return sprintf(
            "Session[%s]: %d items, %d bytes, started=%s",
            $stats['id'],
            $stats['data_count'],
            $size,
            $stats['started'] ? 'yes' : 'no'
        );
    }

    /**
     * Session-Statistiken für Debugging
     */
    public function getStats(): array
    {
        $this->ensureStarted();

        return [
            'id' => $this->getId(),
            'started' => $this->started,
            'status' => session_status(),
            'name' => session_name(),
            'save_path' => session_save_path(),
            'cookie_params' => session_get_cookie_params(),
            'data_count' => count($_SESSION),
            'framework_data_count' => count($_SESSION[self::FRAMEWORK_NAMESPACE] ?? []),
            'flash_data_count' => count($_SESSION[self::FLASH_NAMESPACE] ?? []),
            'memory_usage' => memory_get_usage(true),
            'config' => $this->config,
        ];
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
     * Session-Größe in Bytes (approximiert)
     */
    public function getSize(): int
    {
        $this->ensureStarted();
        return strlen(serialize($_SESSION));
    }

    /**
     * Destruktor - Session wird automatisch gespeichert
     */
    public function __destruct()
    {
        // Session wird automatisch von PHP gespeichert
        // Hier könnten zusätzliche Cleanup-Operationen stehen
    }
}