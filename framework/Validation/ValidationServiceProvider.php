<?php
declare(strict_types=1);

namespace Framework\Validation;

use Framework\Core\AbstractServiceProvider;
use Framework\Core\ApplicationKernel;
use Framework\Core\ServiceContainer;
use Framework\Database\ConnectionManager;

/**
 * ValidationServiceProvider - Registriert Validation Services
 *
 * MODERNISIERUNGEN:
 * ✅ Type-safe Service Registration
 * ✅ Lazy Loading Support
 * ✅ Modern Dependency Injection
 * ✅ Korrekte AbstractServiceProvider Verwendung
 */
class ValidationServiceProvider extends AbstractServiceProvider
{
    public function __construct(ServiceContainer $container, ApplicationKernel $app)
    {
        parent::__construct($container, $app);
    }

    /**
     * Dependency Validation - Prüft ob ConnectionManager verfügbar ist
     */
    #[\Override]
    protected function validateDependencies(): void
    {
        if (!$this->has(ConnectionManager::class)) {
            throw new \RuntimeException(
                'ValidationServiceProvider requires ConnectionManager. ' .
                'Please ensure DatabaseServiceProvider is registered first.'
            );
        }
    }

    /**
     * Registriert Validation Services
     */
    #[\Override]
    protected function registerServices(): void
    {
        // ValidatorFactory als Singleton registrieren
        $this->singleton(ValidatorFactory::class, function (ServiceContainer $container) {
            return new ValidatorFactory(
                $container->get(ConnectionManager::class)
            );
        });

        // Validator als Transient (neue Instanz pro Request)
        $this->transient(Validator::class, function (ServiceContainer $container) {
            return new Validator(
                $container->get(ConnectionManager::class)
            );
        });
    }

    /**
     * Interface Bindings - Für Dependency Injection
     */
    #[\Override]
    protected function bindInterfaces(): void
    {
        // Hier könnten Rule-Interfaces gebunden werden
        // Beispiel: $this->bind(RuleInterface::class, CustomRule::class);
    }

    /**
     * Custom Rules registrieren (Extension Point)
     */
    private function registerCustomRules(): void
    {
        // Beispiel für Custom Rule Registration
        // Kann von Anwendungen überschrieben werden

        // $this->singleton(CustomRule::class);
        // RuleRegistry::register('custom_rule', CustomRule::class);
    }
}