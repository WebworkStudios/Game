<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Core\AbstractServiceProvider;
use Framework\Core\ConfigValidation;

/**
 * Security Service Provider - Registriert Security Services im Framework
 *
 * BEREINIGT: Verwendet ConfigValidation Trait, eliminiert Code-Duplikation
 */
class SecurityServiceProvider extends AbstractServiceProvider
{
    use ConfigValidation;

    /**
     * Validiert Security-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // Config-Validierung (eliminiert die vorherige Duplikation)
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
        $this->registerCsrf();
        $this->registerMiddlewares();
    }

    /**
     * Registriert Session als Singleton
     */
    private function registerSession(): void
    {
        $this->singleton(Session::class, function () {
            // Verwendet die neue loadAndValidateConfig() Methode
            $config = $this->loadAndValidateConfig('security');
            return new Session($config['session'] ?? []);
        });
    }

    /**
     * Registriert Session Security
     */
    private function registerSessionSecurity(): void
    {
        $this->singleton(SessionSecurity::class, function () {
            return new SessionSecurity($this->get(Session::class));
        });
    }

    /**
     * Registriert CSRF Protection
     */
    private function registerCsrf(): void
    {
        $this->singleton(Csrf::class, function () {
            return new Csrf($this->get(Session::class));
        });
    }

    /**
     * Registriert Security Middlewares
     */
    private function registerMiddlewares(): void
    {
        // Middleware-Registrierung falls benötigt
    }

    /**
     * Validiert Session-Verzeichnis (Security-spezifisch)
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
     * Bindet Security-Interfaces
     */
    protected function bindInterfaces(): void
    {
        // Hier können Security-Interfaces gebunden werden
        // $this->bind(AuthenticationInterface::class, Authentication::class);
    }
}