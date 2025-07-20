<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Router;
use Throwable;

/**
 * ApplicationKernel - VERBESSERT: Robustere Debug-Konfiguration
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
     * Bootstrap Framework - VERBESSERT: Debug-Config zuerst laden
     */
    private function bootstrap(): void
    {
        $this->loadDebugConfigEarly();

        $environmentManager = new EnvironmentManager();
        $environmentManager->setup();

        $coreRegistrar = new CoreServiceRegistrar($this->container, $this->basePath);
        $coreRegistrar->registerAll($this);

        $this->loadApplicationConfig();

        $providerRegistry = new ServiceProviderRegistry($this->container, $this);
        $providerRegistry->registerAll();

        $this->setupRouter();
    }

    /**
     * NEUE METHODE: Lädt Debug-Config früh und robust
     */
    private function loadDebugConfigEarly(): void
    {
        try {
            // Direktes Config-Loading ohne ConfigManager Dependency
            $configPath = $this->basePath . '/app/Config/app.php';

            if (file_exists($configPath)) {
                $config = require $configPath;

                if (is_array($config) && isset($config['debug'])) {
                    $this->setDebug((bool)$config['debug']);

                }
            }

        } catch (Throwable $e) {
            $this->setDebug(false); // Explizit auf false setzen
        }
    }

    /**
     * VERBESSERT: Lädt vollständige App-Config mit besserer Exception-Behandlung
     */
    /**
     * BEREINIGT: Lädt vollständige App-Config ohne Debug-Bloat
     */
    private function loadApplicationConfig(): void
    {
        try {
            /** @var ConfigManager $configManager */
            $configManager = $this->container->get(ConfigManager::class);
            $config = $configManager->get('app/Config/app.php');

            // Debug-Modus konsistent setzen (ohne unnötige Logs)
            if (isset($config['debug'])) {
                $this->setDebug((bool) $config['debug']);
            }

            // Environment-Setup falls Konfiguration vorhanden
            if ($this->shouldUpdateEnvironment($config)) {
                $environmentManager = new EnvironmentManager();
                $environmentManager->setup($config);
            }

        } catch (ConfigNotFoundException $e) {
            // Strukturiertes Logging ohne Emoji
            error_log(sprintf(
                'Configuration file not found: %s (using defaults)',
                $e->getMessage()
            ));

        } catch (Throwable $e) {
            // Minimales strukturiertes Error-Logging
            error_log(sprintf(
                'Config loading failed: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            // Fallback zu sicheren Defaults
            $this->setDebug(false);
        }
    }

    /**
     * NEUE HILFSMETHODE: Prüft ob Environment-Update notwendig
     */
    private function shouldUpdateEnvironment(array $config): bool
    {
        return isset($config['timezone'])
            || isset($config['charset'])
            || isset($config['memory_limit']);
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
     * VERBESSERT: Exception-Handler mit besserem Debug-Logging
     */
    private function handleException(Throwable $e, Request $request): Response
    {
        // Debug-Logging der Exception
        if ($this->debug) {
            error_log("🚨 Exception in handleRequest: " . $e->getMessage());
            error_log("   URI: " . $request->getUri());
            error_log("   Method: " . $request->getMethod()->value);
        }

        // Custom Error Handler falls vorhanden
        if ($this->errorHandler) {
            $handler = $this->errorHandler;
            $customResponse = $handler($e, $request);

            if ($customResponse instanceof Response) {
                return $customResponse;
            }
        }

        // Default Error Response - mit Debug-Info wenn Debug aktiv
        $message = $this->debug ?
            $e->getMessage() . "\n\n" . $e->getTraceAsString() :
            'Internal Server Error';

        return new Response(HttpStatus::INTERNAL_SERVER_ERROR, [], $message);
    }

    /**
     * Gibt Debug-Status zurück
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * VERBESSERT: Setzt Debug-Modus mit Logging
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

    /**
     * Gibt Application Base Path zurück
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Baut vollständigen Pfad basierend auf Base Path
     */
    public function path(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Gibt Service Container zurück
     */
    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }
}