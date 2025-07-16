<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Core\AbstractServiceProvider;
use Framework\Core\ConfigValidation;
use Framework\Http\ResponseFactory;

/**
 * Security Service Provider - Registriert refactorierte Security Services
 *
 * REFACTORED: Angepasst für die neue Session-Architektur
 */
class SecurityServiceProvider extends AbstractServiceProvider
{
    use ConfigValidation;

    /**
     * Validiert Security-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // Config-Validierung
        $this->ensureConfigExists('security');

        // Security-spezifische Validierungen
        $this->validateSessionDirectory();
    }

    /**
     * Registriert alle Security Services
     */
    protected function registerServices(): void
    {
        $this->registerSession();
        $this->registerSessionSecurity();
        $this->registerSessionMiddleware();
        $this->registerCsrf();
    }

    /**
     * Registriert Session als Singleton (Pure Data Layer)
     */
    private function registerSession(): void
    {
        $this->singleton(Session::class, function () {
            $config = $this->loadAndValidateConfig('security');
            return new Session($config['session'] ?? []);
        });
    }

    /**
     * Registriert SessionSecurity als Singleton (Security Layer)
     */
    private function registerSessionSecurity(): void
    {
        $this->singleton(SessionSecurity::class, function () {
            $config = $this->loadAndValidateConfig('security');

            $sessionSecurity = new SessionSecurity($this->get(Session::class));

            // Konfiguration anwenden falls vorhanden
            if (isset($config['session_security'])) {
                $sessionSecurity->setConfig($config['session_security']);
            }

            return $sessionSecurity;
        });
    }

    /**
     * Registriert SessionMiddleware als Singleton (Orchestration Layer)
     */
    private function registerSessionMiddleware(): void
    {
        $this->singleton(SessionMiddleware::class, function () {
            $config = $this->loadAndValidateConfig('security');

            $middleware = new SessionMiddleware(
                $this->get(Session::class),
                $this->get(SessionSecurity::class),
                $this->get(ResponseFactory::class)
            );

            // Middleware-Konfiguration anwenden falls vorhanden
            if (isset($config['session_middleware'])) {
                $middleware->setConfig($config['session_middleware']);
            }

            return $middleware;
        });
    }

    /**
     * Registriert CSRF Protection (unverändert)
     */
    private function registerCsrf(): void
    {
        $this->singleton(Csrf::class, function () {
            return new Csrf($this->get(Session::class));
        });
    }

    /**
     * Validiert Session-Verzeichnis
     */
    private function validateSessionDirectory(): void
    {
        $config = $this->loadAndValidateConfig('security');

        if (isset($config['session']['save_path'])) {
            $sessionPath = $this->basePath($config['session']['save_path'] ?? 'storage/sessions');

            if (!is_dir($sessionPath) && !mkdir($sessionPath, 0755, true)) {
                throw new \RuntimeException("Cannot create session directory: {$sessionPath}");
            }
        }
    }

    /**
     * Bindet Security-Interfaces (für zukünftige Erweiterungen)
     */
    protected function bindInterfaces(): void
    {
        // Hier können Security-Interfaces gebunden werden
        // $this->bind(AuthenticationInterface::class, Authentication::class);
    }
}