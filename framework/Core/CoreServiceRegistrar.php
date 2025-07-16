<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Http\ResponseFactory;
use Framework\Routing\Router;
use Framework\Routing\RouterCache;
use Framework\Templating\TemplateEngine;
use Framework\Templating\ViewRenderer;

/**
 * CoreServiceRegistrar - Registriert Framework Core Services
 *
 * Verantwortlichkeiten:
 * - Container, ConfigManager, Router, etc. registrieren
 * - Core Service Abhängigkeiten verwalten
 * - Framework-essenzielle Services bereitstellen
 */
class CoreServiceRegistrar
{
    private ServiceContainer $container;
    private string $basePath;

    public function __construct(ServiceContainer $container, string $basePath)
    {
        $this->container = $container;
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Registriert alle Core Services
     */
    public function registerAll(ApplicationKernel $app): void
    {
        $this->registerContainer();
        $this->registerApplication($app);
        $this->registerConfigManager();
        $this->registerResponseFactory();
        $this->registerRouterServices();
    }

    /**
     * Registriert den Service Container sich selbst
     */
    private function registerContainer(): void
    {
        $this->container->instance(ServiceContainer::class, $this->container);
    }

    /**
     * Registriert die Application Instanz
     */
    private function registerApplication(ApplicationKernel $app): void
    {
        $this->container->instance(ApplicationKernel::class, $app);
    }

    /**
     * Registriert ConfigManager als Singleton
     */
    private function registerConfigManager(): void
    {
        $this->container->singleton(ConfigManager::class, function () {
            return new ConfigManager($this->basePath);
        });
    }

    /**
     * Registriert ResponseFactory als Singleton
     *
     * Abhängig von ViewRenderer und TemplateEngine
     */
    private function registerResponseFactory(): void
    {
        $this->container->singleton(ResponseFactory::class, function (ServiceContainer $container) {
            return new ResponseFactory(
                viewRenderer: $container->get(ViewRenderer::class),
                engine: $container->get(TemplateEngine::class)
            );
        });
    }

    /**
     * Registriert Router und RouterCache Services
     */
    private function registerRouterServices(): void
    {
        $this->registerRouterCache();
        $this->registerRouter();
    }

    /**
     * Registriert RouterCache als Singleton
     */
    private function registerRouterCache(): void
    {
        $this->container->singleton(RouterCache::class, function () {
            return new RouterCache(
                cacheFile: $this->basePath . '/storage/cache/routes.php',
                actionsPath: $this->basePath . '/app/Actions'
            );
        });
    }

    /**
     * Registriert Router als Singleton
     */
    private function registerRouter(): void
    {
        $this->container->singleton(Router::class, function (ServiceContainer $container) {
            return new Router(
                container: $container,
                cache: $container->get(RouterCache::class)
            );
        });
    }

    /**
     * Erweitert Core Services um zusätzliche Services
     *
     * Erlaubt es, weitere Core Services zu registrieren ohne
     * die Hauptklasse zu modifizieren
     */
    public function registerAdditionalServices(array $services): void
    {
        foreach ($services as $abstract => $concrete) {
            if (is_string($abstract)) {
                // Assoziatives Array: Service Name => Factory/Class
                $this->container->singleton($abstract, $concrete);
            } else {
                // Numerisches Array: Nur Class Names
                $this->container->singleton($concrete, $concrete);
            }
        }
    }

    /**
     * Prüft ob alle essentiellen Core Services registriert sind
     *
     * @return array Fehlende Services
     */
    public function validateCoreServices(): array
    {
        $requiredServices = [
            ServiceContainer::class,
            ApplicationKernel::class,
            ConfigManager::class,
            RouterCache::class,
            Router::class,
        ];

        $missing = [];
        foreach ($requiredServices as $service) {
            if (!$this->container->has($service)) {
                $missing[] = $service;
            }
        }

        return $missing;
    }
}