<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Router;
use Throwable;

/**
 * ApplicationKernel - Schlanke Hauptklasse des Frameworks
 *
 * Verantwortlichkeiten:
 * - Framework Bootstrap orchestrieren
 * - HTTP Request Handling
 * - Error Handling
 * - Container Access (minimal)
 */
class ApplicationKernel
{
    private ServiceContainer $container;
    private Router $router;
    private string $basePath;
    private bool $debug = false;

    /** @var callable|null */
    private $errorHandler = null;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->container = new ServiceContainer();

        $this->bootstrap();
    }

    /**
     * Bootstrap Framework - Orchestriert alle Manager
     */
    private function bootstrap(): void
    {
        // 1. Environment Setup
        $environmentManager = new EnvironmentManager();
        $environmentManager->setup();

        // 2. Core Services registrieren
        $coreRegistrar = new CoreServiceRegistrar($this->container, $this->basePath);
        $coreRegistrar->registerAll($this);

        // 3. App-Config laden und Environment anpassen
        $this->loadApplicationConfig();

        // 4. Service Provider registrieren
        $providerRegistry = new ServiceProviderRegistry($this->container, $this);
        $providerRegistry->registerAll();

        // 5. Router Setup
        $this->setupRouter();
    }

    /**
     * Lädt Application Config und passt Environment an
     */
    private function loadApplicationConfig(): void
    {
        try {
            /** @var ConfigManager $configManager */
            $configManager = $this->container->get(ConfigManager::class);
            $config = $configManager->get('app/Config/app.php');

            // Debug-Modus setzen
            $this->setDebug($config['debug'] ?? false);

            // Environment anpassen falls nötig
            if (isset($config['timezone']) || isset($config['charset']) || isset($config['memory_limit'])) {
                $environmentManager = new EnvironmentManager();
                $environmentManager->setup($config);
            }

        } catch (\Exception) {
            // Config nicht gefunden - Default-Werte verwenden
            // Das ist normal beim ersten Start oder in Tests
        }
    }

    /**
     * Holt Service aus Container
     */
    public function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    /**
     * Setup Router
     */
    private function setupRouter(): void
    {
        $this->router = $this->container->get(Router::class);

        // Hier könnten globale Middleware registriert werden:
        // $this->registerGlobalMiddleware();
    }

    /**
     * Handles eingehende HTTP-Requests
     */
    public function handleRequest(Request $request): Response
    {
        try {
            return $this->router->handle($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Exception-Handler
     */
    private function handleException(Throwable $e, Request $request): Response
    {
        // Custom Error Handler falls vorhanden
        if ($this->errorHandler) {
            $handler = $this->errorHandler;
            $customResponse = $handler($e, $request);

            if ($customResponse instanceof Response) {
                return $customResponse;
            }
        }

        // Default Error Response
        $message = $this->debug ?
            $e->getMessage() . "\n\n" . $e->getTraceAsString() :
            'Internal Server Error';

        return new Response(HttpStatus::INTERNAL_SERVER_ERROR, [], $message);
    }

    // ===================================================================
    // Configuration & Debugging
    // ===================================================================

    /**
     * Gibt Debug-Status zurück
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Setzt Debug-Modus
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Setzt Custom Error Handler
     */
    public function setErrorHandler(callable $handler): self
    {
        $this->errorHandler = $handler;
        return $this;
    }

    // ===================================================================
    // Path Utilities
    // ===================================================================

    /**
     * Gibt Application Base Path zurück
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Alias für path() für BC compatibility
     */
    public function basePath(string $path = ''): string
    {
        return $this->path($path);
    }

    /**
     * Baut vollständigen Pfad basierend auf Base Path
     */
    public function path(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    // ===================================================================
    // Container Access (Minimal)
    // ===================================================================

    /**
     * Gibt Service Container zurück
     */
    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }

    /**
     * Registriert globale Middleware (optional)
     *
     * Kann überschrieben oder erweitert werden für spezifische Anforderungen
     */
    protected function registerGlobalMiddleware(): void
    {
        // Beispiel:
        // $this->router->addGlobalMiddleware(SomeMiddleware::class);
    }
}