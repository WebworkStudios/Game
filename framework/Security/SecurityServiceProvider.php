<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Core\AbstractServiceProvider;
use Framework\Routing\RouterCache;

/**
 * Security Service Provider - Registriert Security Services im Framework
 *
 * BEREINIGT: Keine Default-Provider mehr - Config-Dateien sind die einzige Quelle
 */
class SecurityServiceProvider extends AbstractServiceProvider
{
    private const string CONFIG_PATH = 'app/Config/security.php';

    /**
     * Validiert Security-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // Prüfe ob Config-Datei existiert
        if (!$this->configExists()) {
            throw new \RuntimeException(
                "Security config file not found: " . self::CONFIG_PATH . "\n" .
                "Please create this file or run: php artisan config:publish security"
            );
        }

        // Prüfe ob Session-Verzeichnis existiert/erstellt werden kann
        $config = $this->getConfig(self::CONFIG_PATH);

        if (isset($config['session']['save_path'])) {
            $sessionPath = $this->basePath($config['session']['save_path'] ?? 'storage/sessions');
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
            $config = $this->getConfig(self::CONFIG_PATH);
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
            $config = $this->getConfig(self::CONFIG_PATH);

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
     * Prüft ob Config-Datei existiert
     */
    private function configExists(): bool
    {
        return file_exists($this->basePath(self::CONFIG_PATH));
    }
}