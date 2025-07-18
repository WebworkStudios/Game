<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Assets\JavaScriptAssetServiceProvider;
use Framework\Database\DatabaseServiceProvider;
use Framework\Localization\LocalizationServiceProvider;
use Framework\Security\SecurityServiceProvider;
use Framework\Templating\TemplatingServiceProvider;
use Framework\Validation\ValidationServiceProvider;
use RuntimeException;

/**
 * ServiceProviderRegistry - Orchestriert alle Service Provider
 *
 * ERWEITERT: Boot-Methoden Support für Filter-Registrierung
 *
 * Verantwortlichkeiten:
 * - Service Provider in korrekter Reihenfolge registrieren
 * - Provider-Abhängigkeiten verwalten
 * - Boot-Methoden nach Registration aufrufen
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
        JavaScriptAssetServiceProvider::class, // JavaScript-Filter werden hier registriert
    ];

    /** @var array<string> Zusätzliche App-spezifische Provider */
    private array $appProviders = [];

    /** @var array<AbstractServiceProvider> Registrierte Provider-Instanzen */
    private array $registeredProviders = [];

    public function __construct(ServiceContainer $container, ApplicationKernel $app)
    {
        $this->container = $container;
        $this->app = $app;
    }

    /**
     * Registriert alle Service Provider UND ruft Boot-Methoden auf
     */
    public function registerAll(): void
    {
        // Phase 1: Alle Provider registrieren
        $this->registerProviders();

        // Phase 2: Boot-Methoden aufrufen (für Filter-Registrierung etc.)
        $this->bootProviders();
    }

    /**
     * Phase 1: Registriert alle Service Provider
     */
    private function registerProviders(): void
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
     * Phase 2: Ruft Boot-Methoden aller Provider auf
     */
    private function bootProviders(): void
    {
        foreach ($this->registeredProviders as $provider) {
            if (method_exists($provider, 'boot')) {
                try {
                    $provider->boot();
                } catch (\Throwable $e) {
                    // Boot-Fehler loggen, aber nicht Framework stoppen
                    if (($_ENV['APP_DEBUG'] ?? false) === 'true') {
                        error_log("Provider boot failed: " . get_class($provider) . " - " . $e->getMessage());
                    }
                }
            }
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

        // Provider registrieren
        $provider->register();

        // Provider-Instanz für Boot-Phase speichern
        $this->registeredProviders[] = $provider;
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
     * Gibt registrierte Provider-Instanzen zurück (für Debug)
     *
     * @return array<AbstractServiceProvider>
     */
    public function getRegisteredProviders(): array
    {
        return $this->registeredProviders;
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

    /**
     * Prüft ob alle kritischen Provider registriert sind
     */
    public function validateRegistration(): array
    {
        $missing = [];
        $requiredProviders = [
            SecurityServiceProvider::class,
            TemplatingServiceProvider::class,
        ];

        foreach ($requiredProviders as $required) {
            if (!in_array($required, $this->providers)) {
                $missing[] = $required;
            }
        }

        return $missing;
    }

    /**
     * Debug-Informationen über Provider-Registrierung
     */
    public function getDebugInfo(): array
    {
        return [
            'framework_providers' => $this->providers,
            'app_providers' => $this->appProviders,
            'registered_count' => count($this->registeredProviders),
            'registered_classes' => array_map(fn($p) => get_class($p), $this->registeredProviders),
            'missing_providers' => $this->validateRegistration()
        ];
    }
}