<?php

declare(strict_types=1);

namespace Framework\Core;

/**
 * Abstract Service Provider - Basis-Klasse für alle Service Provider
 *
 * UPDATED: Constructor verwendet ApplicationKernel statt Application
 */
abstract class AbstractServiceProvider
{
    protected ServiceContainer $container;
    protected ApplicationKernel $app;

    public function __construct(ServiceContainer $container, ApplicationKernel $app)  // ← GEÄNDERT
    {
        $this->container = $container;
        $this->app = $app;
    }

    /**
     * Template Method - definiert den festen Ablauf der Service-Registrierung
     */
    public function register(): void
    {
        $this->validateDependencies();
        $this->registerServices();
        $this->bindInterfaces();
    }

    /**
     * Registriert alle Services des Providers
     */
    abstract protected function registerServices(): void;

    /**
     * Bindet Interfaces an konkrete Implementierungen
     */
    protected function bindInterfaces(): void
    {
        // Default: Keine Interface-Bindings
    }

    /**
     * Validiert benötigte Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // Default: Keine Validierung
    }

    // ===================================================================
    // Container Helper Methods (unverändert)
    // ===================================================================

    protected function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    protected function transient(string $abstract, callable|string|null $concrete = null): void
    {
        $this->container->transient($abstract, $concrete);
    }

    protected function instance(string $abstract, object $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    protected function bind(string $interface, string $implementation): void
    {
        $this->container->bind($interface, $implementation);
    }

    protected function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    protected function has(string $abstract): bool
    {
        return $this->container->has($abstract);
    }

    // ===================================================================
    // Path & Config Methods (unverändert)
    // ===================================================================

    protected function basePath(string $path = ''): string
    {
        return $this->app->getBasePath() . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Lädt Konfiguration über ConfigManager
     */
    protected function getConfig(string $configPath, ?callable $defaultProvider = null, array $requiredKeys = []): array
    {
        $configManager = $this->get(ConfigManager::class);
        return $configManager->get($configPath, $defaultProvider, $requiredKeys);
    }

    /**
     * Publiziert Config-Datei über ConfigManager
     */
    protected function publishConfig(string $configPath, callable $contentProvider): bool
    {
        $configManager = $this->get(ConfigManager::class);
        return $configManager->publish($configPath, $contentProvider);
    }
}
