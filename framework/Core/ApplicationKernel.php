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
 *
 * FIXES:
 * ✅ Debug-Config wird früher geladen
 * ✅ Fallback-Config-Loading ohne ConfigManager
 * ✅ Besseres Exception-Handling
 * ✅ Debug-Logging für Troubleshooting
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
        // 1. DEBUG-CONFIG ZUERST LADEN (FIX)
        $this->loadDebugConfigEarly();

        // 2. Environment Setup
        $environmentManager = new EnvironmentManager();
        $environmentManager->setup();

        // 3. Core Services registrieren
        $coreRegistrar = new CoreServiceRegistrar($this->container, $this->basePath);
        $coreRegistrar->registerAll($this);

        // 4. Vollständige App-Config laden (mit ConfigManager)
        $this->loadApplicationConfig();

        // 5. Service Provider registrieren
        $providerRegistry = new ServiceProviderRegistry($this->container, $this);
        $providerRegistry->registerAll();

        // 6. Router Setup
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
                    $this->setDebug((bool) $config['debug']);

                    // Debug-Logging falls Debug aktiv
                    if ($this->debug) {
                        error_log("✅ Debug-Modus aktiviert aus: {$configPath}");
                    }
                } else {
                    error_log("⚠️ Debug-Key nicht gefunden in app.php - verwende default (false)");
                }
            } else {
                error_log("⚠️ app/Config/app.php nicht gefunden - Debug bleibt false");
            }

        } catch (Throwable $e) {
            // Robustes Fallback bei jedem Fehler
            error_log("❌ Fehler beim frühen Debug-Config-Loading: " . $e->getMessage());
            $this->setDebug(false); // Explizit auf false setzen
        }
    }

    /**
     * VERBESSERT: Lädt vollständige App-Config mit besserer Exception-Behandlung
     */
    private function loadApplicationConfig(): void
    {
        try {
            /** @var ConfigManager $configManager */
            $configManager = $this->container->get(ConfigManager::class);
            $config = $configManager->get('app/Config/app.php');

            // Debug-Modus erneut setzen (falls ConfigManager andere Werte liefert)
            if (isset($config['debug'])) {
                $debugValue = (bool) $config['debug'];

                if ($debugValue !== $this->debug) {
                    if ($this->debug) {
                        error_log("🔄 Debug-Modus geändert: früh={$this->debug}, ConfigManager={$debugValue}");
                    }
                    $this->setDebug($debugValue);
                }
            }

            // Environment anpassen falls nötig
            if (isset($config['timezone']) || isset($config['charset']) || isset($config['memory_limit'])) {
                $environmentManager = new EnvironmentManager();
                $environmentManager->setup($config);
            }

        } catch (ConfigNotFoundException $e) {
            // Spezifische Behandlung für fehlende Config-Datei
            error_log("❌ Config-Datei nicht gefunden: " . $e->getMessage());

        } catch (Throwable $e) {
            // Generische Exception-Behandlung mit besserer Information
            error_log("❌ Fehler beim Config-Loading: " . $e->getMessage());
            error_log("   Klasse: " . get_class($e));
            error_log("   Datei: " . $e->getFile() . ":" . $e->getLine());

            // Debug-Status beibehalten (wurde bereits früh gesetzt)
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
     * VERBESSERT: Setzt Debug-Modus mit Logging
     */
    public function setDebug(bool $debug): self
    {
        $oldDebug = $this->debug;
        $this->debug = $debug;

        // Logging nur wenn sich der Wert ändert
        if ($oldDebug !== $debug) {
            error_log("🔧 Debug-Modus geändert: {$oldDebug} → {$debug}");
        }

        return $this;
    }

    /**
     * NEUE METHODE: Debug-Status für Troubleshooting
     */
    public function getDebugInfo(): array
    {
        return [
            'debug_mode' => $this->debug,
            'base_path' => $this->basePath,
            'config_file_exists' => file_exists($this->basePath . '/app/Config/app.php'),
            'container_has_services' => $this->container->has(Router::class) && $this->container->has(ConfigManager::class),
            'error_handler_set' => $this->errorHandler !== null,
        ];
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