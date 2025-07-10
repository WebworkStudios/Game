<?php


declare(strict_types=1);

namespace Framework\Validation;

use Framework\Core\Application;
use Framework\Core\ServiceContainer;
use Framework\Database\ConnectionManager;

/**
 * Validation Service Provider - Registriert Validation Services im Framework
 */
class ValidationServiceProvider
{
    public function __construct(
        private readonly ServiceContainer $container,
        private readonly Application      $app,
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
        $this->container->singleton('validator_factory', function (ServiceContainer $container) {
            return function (array $data, array $rules, ?string $connectionName = null) use ($container) {
                $connectionManager = $container->get(ConnectionManager::class);

                return Validator::make($data, $rules, $connectionManager);
            };
        });

        // Alias für einfacheren Zugriff
        $this->container->singleton(ValidatorFactory::class, function (ServiceContainer $container) {
            return $container->get('validator_factory');
        });
    }

    /**
     * Bindet Interfaces (für zukünftige Erweiterungen)
     */
    private function bindInterfaces(): void
    {

    }
}

