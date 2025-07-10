<?php


declare(strict_types=1);

namespace Framework\Validation;

use Framework\Core\Application;
use Framework\Core\ServiceContainer;
use Framework\Database\ConnectionManager;

/**
 * Validation Service Provider - Registriert Validation Services im Framework
 */
readonly class ValidationServiceProvider
{
    public function __construct(
        private ServiceContainer $container
    )
    {
    }

    /**
     * Registriert alle Validation Services
     */
    public function register(): void
    {
        $this->registerValidator();
        $this->registerValidatorFactory();
        $this->bindInterfaces();
    }

    /**
     * Registriert Validator als Transient (neue Instanz pro Request)
     */
    private function registerValidator(): void
    {
        $this->container->transient(Validator::class, function (ServiceContainer $container) {
            return new Validator(
                connectionManager: $container->get(ConnectionManager::class)
            );
        });
    }

    /**
     * Registriert Validator Factory für verschiedene Connections
     */
    private function registerValidatorFactory(): void
    {
        $this->container->singleton(ValidatorFactory::class, function (ServiceContainer $container) {
            return new ValidatorFactory(
                connectionManager: $container->get(ConnectionManager::class)
            );
        });
    }

    /**
     * Bindet Interfaces (für zukünftige Erweiterungen)
     */
    private function bindInterfaces(): void
    {

    }
}

