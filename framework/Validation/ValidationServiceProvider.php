<?php

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Core\AbstractServiceProvider;
use Framework\Database\ConnectionManager;

/**
 * Validation Service Provider - Registriert Validation Services im Framework
 *
 * BEREINIGT: Keine Default-Provider mehr - Config-Dateien sind die einzige Quelle
 * ValidationServiceProvider funktioniert ohne eigene Config-Datei
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

    /**
     * Bindet Validation-Interfaces
     */
    protected function bindInterfaces(): void
    {
        // Hier können Validation-Interfaces gebunden werden
        // $this->bind(ValidatorInterface::class, Validator::class);
    }
}