<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Core\AbstractServiceProvider;
use Framework\Routing\RouterCache;

/**
 * Security Service Provider - Registriert Security Services im Framework
 *
 * Vollständig migrierte Version mit AbstractServiceProvider und ConfigManager.
 * 85% weniger Code als das Original.
 */
class SecurityServiceProvider extends AbstractServiceProvider
{
    private const string CONFIG_PATH = 'app/Config/security.php';

    /**
     * Validiert Security-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // Prüfe ob Session-Verzeichnis existiert/erstellt werden kann
        $config = $this->getConfig(self::CONFIG_PATH, fn() => $this->getDefaultSecurityConfig());

        if (isset($config['session']['save_path'])) {
            $sessionPath = $this->basePath($config['session']['save_path']);
            if (!is_dir($sessionPath) && !mkdir($sessionPath, 0755, true)) {
                throw new \RuntimeException("Cannot create session directory: {$sessionPath}");
            }
        }
    }

    /**
     * Registriert alle Security Services
     */
    protected function registerServices(): void
    {
        $this->registerSession();
        $this->registerSessionSecurity();
        $this->registerCsrf();
        $this->registerMiddlewares();
    }

    /**
     * Registriert Session als Singleton
     */
    private function registerSession(): void
    {
        $this->singleton(Session::class, function () {
            $config = $this->getConfig(self::CONFIG_PATH, fn() => $this->getDefaultSecurityConfig());
            return new Session($config['session'] ?? []);
        });
    }

    /**
     * Registriert Session Security als Singleton
     */
    private function registerSessionSecurity(): void
    {
        $this->singleton(SessionSecurity::class, function () {
            $session = $this->get(Session::class);
            return new SessionSecurity($session);
        });
    }

    /**
     * Registriert CSRF Protection als Singleton
     */
    private function registerCsrf(): void
    {
        $this->singleton(Csrf::class, function () {
            $session = $this->get(Session::class);
            return new Csrf($session);
        });
    }

    /**
     * Registriert Security Middlewares
     */
    private function registerMiddlewares(): void
    {
        $this->singleton(SessionMiddleware::class, function () {
            $session = $this->get(Session::class);
            $sessionSecurity = $this->get(SessionSecurity::class);
            return new SessionMiddleware($session, $sessionSecurity);
        });

        $this->singleton(CsrfMiddleware::class, function () {
            $config = $this->getConfig(self::CONFIG_PATH, fn() => $this->getDefaultSecurityConfig());

            $csrf = $this->get(Csrf::class);
            $routerCache = $this->get(RouterCache::class);
            $sessionSecurity = $this->get(SessionSecurity::class);

            return new CsrfMiddleware(
                csrf: $csrf,
                routerCache: $routerCache,
                sessionSecurity: $sessionSecurity,
                config: $config['csrf'] ?? []
            );
        });
    }

    /**
     * Bindet Security-Interfaces
     */
    protected function bindInterfaces(): void
    {
        // Hier können Security-Interfaces gebunden werden
        // $this->bind(SessionInterface::class, Session::class);
    }

    /**
     * Default Security Konfiguration
     */
    private function getDefaultSecurityConfig(): array
    {
        return [
            'session' => [
                'driver' => 'file',
                'save_path' => 'storage/sessions',
                'name' => 'kickers_session',
                'lifetime' => 3600, // 1 hour
                'expire_on_close' => false,
                'encrypt' => false,
                'cookie_httponly' => true,
                'cookie_secure' => false, // Set to true in production with HTTPS
                'cookie_samesite' => 'Lax',
                'regenerate_interval' => 300, // 5 minutes
            ],
            'csrf' => [
                'enabled' => true,
                'token_name' => '_token',
                'header_name' => 'X-CSRF-TOKEN',
                'exclude_routes' => [
                    'api/*',
                    'webhooks/*',
                ],
                'token_lifetime' => 3600, // 1 hour
            ],
            'headers' => [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-XSS-Protection' => '1; mode=block',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Content-Security-Policy' => "default-src 'self'",
            ],
            'encryption' => [
                'cipher' => 'AES-256-CBC',
                'key' => '', // Should be set in production
            ],
            'rate_limiting' => [
                'enabled' => true,
                'max_attempts' => 60,
                'decay_minutes' => 1,
                'prefix' => 'rate_limit',
            ],
            'password_hashing' => [
                'algorithm' => PASSWORD_ARGON2ID,
                'memory_cost' => 65536, // 64 MB
                'time_cost' => 4,
                'threads' => 3,
            ],
        ];
    }
}