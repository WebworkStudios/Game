<?php


declare(strict_types=1);

namespace Framework\Core;

use Framework\Database\DatabaseServiceProvider;
use Framework\Localization\LocalizationServiceProvider;
use Framework\Security\SecurityServiceProvider;
use Framework\Templating\TemplatingServiceProvider;
use Framework\Validation\ValidationServiceProvider;
use RuntimeException;

/**
 * ServiceProviderRegistry - Orchestriert alle Service Provider
 *
 * Verantwortlichkeiten:
 * - Service Provider in korrekter Reihenfolge registrieren
 * - Provider-Abhängigkeiten verwalten
 * - Provider-Registry erweitern können
 */
class ServiceProviderRegistry
{
    private ServiceContainer $container;
    private ApplicationKernel $app;

    /** @var array<string> Framework Service Provider in Reihenfolge */
    private array $providers = [
        SecurityServiceProvider::class,
        DatabaseServiceProvider::class,
        ValidationServiceProvider::class,
        LocalizationServiceProvider::class,
        TemplatingServiceProvider::class,
    ];

    /** @var array<string> Zusätzliche App-spezifische Provider */
    private array $appProviders = [];

    public function __construct(ServiceContainer $container, ApplicationKernel $app)
    {
        $this->container = $container;
        $this->app = $app;
    }

    /**
     * Registriert alle Service Provider
     */
    public function registerAll(): void
    {
        // Framework Provider zuerst
        foreach ($this->providers as $providerClass) {
            $this->registerProvider($providerClass);
        }

        // App Provider danach
        foreach ($this->appProviders as $providerClass) {
            $this->registerProvider($providerClass);
        }
    }

    /**
     * Registriert einzelnen Service Provider
     */
    public function registerProvider(string $providerClass): void
    {
        if (!class_exists($providerClass)) {
            throw new RuntimeException("Service Provider not found: {$providerClass}");
        }

        $provider = new $providerClass($this->container, $this->app);

        if (!$provider instanceof AbstractServiceProvider) {
            throw new RuntimeException("Provider must extend AbstractServiceProvider: {$providerClass}");
        }

        $provider->register();
    }

    /**
     * Fügt Framework Provider zur Registry hinzu
     *
     * @param string $providerClass Provider Klassenname
     * @param int|null $position Optional: Position in der Reihenfolge
     */
    public function addFrameworkProvider(string $providerClass, ?int $position = null): void
    {
        if (!in_array($providerClass, $this->providers, true)) {
            if ($position !== null) {
                array_splice($this->providers, $position, 0, $providerClass);
            } else {
                $this->providers[] = $providerClass;
            }
        }
    }

    /**
     * Fügt App Provider zur Registry hinzu
     */
    public function addAppProvider(string $providerClass): void
    {
        if (!in_array($providerClass, $this->appProviders, true)) {
            $this->appProviders[] = $providerClass;
        }
    }

    /**
     * Entfernt Framework Provider aus Registry
     */
    public function removeFrameworkProvider(string $providerClass): void
    {
        $this->providers = array_values(array_filter(
            $this->providers,
            fn($provider) => $provider !== $providerClass
        ));
    }

    /**
     * Entfernt App Provider aus Registry
     */
    public function removeAppProvider(string $providerClass): void
    {
        $this->appProviders = array_values(array_filter(
            $this->appProviders,
            fn($provider) => $provider !== $providerClass
        ));
    }

    /**
     * Gibt alle registrierten Provider zurück
     *
     * @return array{framework: array<string>, app: array<string>}
     */
    public function getProviders(): array
    {
        return [
            'framework' => $this->providers,
            'app' => $this->appProviders,
        ];
    }

    /**
     * Lädt Provider aus Config-Datei
     *
     * Erwartet Config-Format:
     * [
     *   'framework' => [ProviderClass::class, ...],
     *   'app' => [AppProviderClass::class, ...]
     * ]
     */
    public function loadFromConfig(array $config): void
    {
        if (isset($config['framework']) && is_array($config['framework'])) {
            $this->providers = array_merge($this->providers, $config['framework']);
        }

        if (isset($config['app']) && is_array($config['app'])) {
            $this->appProviders = array_merge($this->appProviders, $config['app']);
        }
    }
}