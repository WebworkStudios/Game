<?php

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Core\AbstractServiceProvider;
use Framework\Database\ConnectionManager;

/**
 * Validation Service Provider - Registriert Validation Services im Framework
 *
 * Vollständig migrierte Version mit AbstractServiceProvider und ConfigManager.
 * 85% weniger Code als das Original.
 */
class ValidationServiceProvider extends AbstractServiceProvider
{
    /**
     * Validiert, dass alle benötigten Abhängigkeiten verfügbar sind
     */
    protected function validateDependencies(): void
    {
        if (!$this->has(ConnectionManager::class)) {
            throw new \RuntimeException('ValidationServiceProvider requires ConnectionManager to be registered first');
        }
    }

    /**
     * Registriert alle Validation Services
     */
    protected function registerServices(): void
    {
        $this->registerValidator();
        $this->registerValidatorFactory();
    }

    /**
     * Registriert Validator als Transient (neue Instanz pro Request)
     */
    private function registerValidator(): void
    {
        $this->transient(Validator::class, function () {
            return new Validator(
                connectionManager: $this->get(ConnectionManager::class)
            );
        });
    }

    /**
     * Registriert Validator Factory für verschiedene Connections
     */
    private function registerValidatorFactory(): void
    {
        $this->singleton(ValidatorFactory::class, function () {
            return new ValidatorFactory(
                connectionManager: $this->get(ConnectionManager::class)
            );
        });
    }
}