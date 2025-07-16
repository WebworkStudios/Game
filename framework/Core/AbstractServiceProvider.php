<?php

declare(strict_types=1);

namespace Framework\Core;

/**
 * Abstract Service Provider - Basis-Klasse für alle Service Provider
 *
 * Implementiert das Template Method Pattern für konsistente Service-Registrierung:
 * 1. Abhängigkeiten validieren (optional)
 * 2. Services registrieren (verpflichtend)
 * 3. Interfaces binden (optional)
 *
 * Verhindert Code-Duplikation zwischen Providern und sorgt für einheitlichen Ablauf.
 */
abstract class AbstractServiceProvider
{
    protected ServiceContainer $container;
    protected Application $app;

    public function __construct(ServiceContainer $container, Application $app)
    {
        $this->container = $container;
        $this->app = $app;
    }

    /**
     * Template Method - definiert den festen Ablauf der Service-Registrierung
     *
     * Diese Methode ist final um zu verhindern, dass Provider den standardisierten
     * Ablauf versehentlich überschreiben.
     */
    final public function register(): void
    {
        $this->validateDependencies();
        $this->registerServices();
        $this->bindInterfaces();
    }

    /**
     * Registriert alle Services des Providers
     *
     * Muss von jedem Provider implementiert werden.
     * Hier werden alle Singleton/Transient Services über den Container registriert.
     */
    abstract protected function registerServices(): void;

    /**
     * Bindet Interfaces an konkrete Implementierungen
     *
     * Optional - nur implementieren wenn der Provider Interface-Bindings benötigt.
     * Beispiel: $this->container->bind(UserRepositoryInterface::class, UserRepository::class);
     */
    protected function bindInterfaces(): void
    {
        // Default: Keine Interface-Bindings
    }

    /**
     * Validiert benötigte Abhängigkeiten
     *
     * Optional - nur implementieren wenn der Provider spezielle Abhängigkeiten prüfen muss.
     * Beispiel: Prüfung ob bestimmte Extensions installiert sind.
     */
    protected function validateDependencies(): void
    {
        // Default: Keine Validierung
    }

    /**
     * Hilfsmethode: Registriert Service als Singleton
     */
    protected function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    /**
     * Hilfsmethode: Registriert Service als Transient
     */
    protected function transient(string $abstract, callable|string|null $concrete = null): void
    {
        $this->container->transient($abstract, $concrete);
    }

    /**
     * Hilfsmethode: Registriert bereits erstellte Instanz
     */
    protected function instance(string $abstract, object $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    /**
     * Hilfsmethode: Bindet Interface an Implementierung
     */
    protected function bind(string $interface, string $implementation): void
    {
        $this->container->bind($interface, $implementation);
    }

    /**
     * Hilfsmethode: Holt Service aus Container
     */
    protected function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    /**
     * Hilfsmethode: Prüft ob Service registriert ist
     */
    protected function has(string $abstract): bool
    {
        return $this->container->has($abstract);
    }

    /**
     * Hilfsmethode: Holt Application Base Path
     */
    protected function basePath(string $path = ''): string
    {
        return $this->app->getBasePath() . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Hilfsmethode: Lädt Konfiguration über ConfigManager
     *
     * @param string $configPath Relativer Pfad zur Config-Datei
     * @param callable|null $defaultProvider Optional: Factory für Default-Config
     * @param array $requiredKeys Optional: Required Config Keys
     */
    protected function getConfig(string $configPath, ?callable $defaultProvider = null, array $requiredKeys = []): array
    {
        $configManager = $this->get(ConfigManager::class);
        return $configManager->get($configPath, $defaultProvider, $requiredKeys);
    }

    /**
     * Hilfsmethode: Publiziert Config-Datei über ConfigManager
     */
    protected function publishConfig(string $configPath, callable $contentProvider): bool
    {
        $configManager = $this->get(ConfigManager::class);
        return $configManager->publish($configPath, $contentProvider);
    }
}