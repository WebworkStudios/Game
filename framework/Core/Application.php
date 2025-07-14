<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Database\ConnectionManager;
use Framework\Database\DatabaseServiceProvider;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Localization\LocalizationServiceProvider;
use Framework\Localization\Translator;
use Framework\Routing\Router;
use Framework\Routing\RouterCache;
use Framework\Security\Csrf;
use Framework\Security\SecurityServiceProvider;
use Framework\Security\Session;
use Framework\Security\SessionSecurity;
use Framework\Templating\TemplateEngine;
use Framework\Templating\TemplatingServiceProvider;
use Framework\Templating\ViewRenderer;
use Framework\Validation\ValidationServiceProvider;
use Framework\Validation\Validator;
use Framework\Validation\ValidatorFactory;
use RuntimeException;
use Throwable;

/**
 * Application - Bootstrap und Orchestrierung des Frameworks
 * Core Framework Application with Database, Security, Validation, Templating & Localization
 */
class Application
{
    private const string DEFAULT_TIMEZONE = 'UTC';
    private const string DEFAULT_CHARSET = 'UTF-8';


    private ServiceContainer $container;
    private Router $router;
    private bool $debug = false;
    private string $basePath;

    /** @var callable|null */
    private $errorHandler = null;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->container = new ServiceContainer();

        $this->bootstrap();
    }

    /**
     * Bootstrap Framework Components
     */
    private function bootstrap(): void
    {
        $this->setupEnvironment();
        $this->loadAppConfig();
        $this->registerCoreServices();
        $this->registerDatabaseServices();
        $this->registerSecurityServices();
        $this->registerValidationServices();
        $this->registerLocalizationServices();
        $this->registerTemplatingServices();
        $this->setupRouter();
    }

    /**
     * Setup der PHP-Umgebung
     */
    private function setupEnvironment(): void
    {
        // Error Reporting
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        // Timezone
        date_default_timezone_set(self::DEFAULT_TIMEZONE);

        // Charset
        ini_set('default_charset', self::DEFAULT_CHARSET);
        mb_internal_encoding(self::DEFAULT_CHARSET);

        // Session wird durch SecurityServiceProvider verwaltet
    }

    /**
     * Load application configuration
     */
    private function loadAppConfig(): void
    {
        try {
            $config = $this->loadConfig('app/Config/app.php');

            // Debug-Modus setzen
            $this->setDebug($config['debug'] ?? false);

            // Timezone setzen (überschreibt setupEnvironment)
            if (isset($config['timezone'])) {
                date_default_timezone_set($config['timezone']);
            }
        } catch (\Exception) {
            // Config nicht gefunden - Default-Werte aus setupEnvironment verwenden
        }
    }

    /**
     * Lädt Konfiguration aus Datei
     */
    public function loadConfig(string $configFile): array
    {
        $fullPath = $this->basePath . '/' . ltrim($configFile, '/');

        if (!file_exists($fullPath)) {
            throw new RuntimeException("Config file not found: {$fullPath}");
        }

        $config = require $fullPath;

        if (!is_array($config)) {
            throw new RuntimeException("Config file must return array: {$fullPath}");
        }

        return $config;
    }

    /**
     * Registriert Core-Services im Container
     */
    private function registerCoreServices(): void
    {
        // Container sich selbst registrieren
        $this->container->instance(ServiceContainer::class, $this->container);

        // Application sich selbst registrieren
        $this->container->instance(Application::class, $this);
        $this->container->instance(static::class, $this);

        // ResponseFactory registrieren
        $this->container->singleton(ResponseFactory::class, function (ServiceContainer $container) {
            return new ResponseFactory(
                viewRenderer: $container->get(ViewRenderer::class),
                engine: $container->get(TemplateEngine::class)
            );
        });

        // RouterCache registrieren
        $this->container->singleton(RouterCache::class, function () {
            return new RouterCache(
                cacheFile: $this->basePath . '/storage/cache/routes.php',
                actionsPath: $this->basePath . '/app/Actions'
            );
        });

        // Router registrieren
        $this->container->singleton(Router::class, function (ServiceContainer $container) {
            return new Router(
                container: $container,
                cache: $container->get(RouterCache::class)
            );
        });
    }

    /**
     * Register Instance
     */
    public function instance(string $abstract, mixed $instance): self
    {
        $this->container->instance($abstract, $instance);
        return $this;
    }

    /**
     * Register Singleton Service
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): self
    {
        $this->container->singleton($abstract, $concrete);
        return $this;
    }

    /**
     * Generic Service Access
     */
    public function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    /**
     * Registriert Database Services
     */
    private function registerDatabaseServices(): void
    {
        $provider = new DatabaseServiceProvider($this->container, $this);
        $provider->register();
    }

    /**
     * Registriert Security Services (Session, CSRF, etc.)
     */
    private function registerSecurityServices(): void
    {
        $provider = new SecurityServiceProvider($this->container, $this);
        $provider->register();

        // Services direkt im Application-Container verfügbar machen
        $this->container->singleton('session', fn() => $this->container->get(Session::class));
        $this->container->singleton('session_security', fn() => $this->container->get(SessionSecurity::class));
        $this->container->singleton('csrf', fn() => $this->container->get(Csrf::class));
    }

    /**
     * Registriert Validation Services
     */
    private function registerValidationServices(): void
    {
        $provider = new ValidationServiceProvider($this->container, $this);
        $provider->register();
    }

    /**
     * Registriert Localization Services
     */
    private function registerLocalizationServices(): void
    {
        $provider = new LocalizationServiceProvider($this->container, $this);
        $provider->register();
    }

    /**
     * Registriert Templating Services
     */
    private function registerTemplatingServices(): void
    {
        $provider = new TemplatingServiceProvider($this->container, $this);
        $provider->register();
    }

    /**
     * Setup Router
     */
    private function setupRouter(): void
    {
        $this->router = $this->container->get(Router::class);

        // Globale Middleware registrieren (falls konfiguriert)
        // $this->router->addGlobalMiddleware(SomeMiddleware::class);
    }



    /**
     * Get application base path
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Build application path
     */
    public function path(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Get base path (alias for getBasePath)
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Get ResponseFactory service
     */
    public function getResponseFactory(): ResponseFactory
    {
        return $this->container->get(ResponseFactory::class);
    }

    /**
     * Force session start (useful for testing)
     */
    public function startSession(): bool
    {
        try {
            $session = $this->get('session');
            return $session->start();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get Container for DI
     */
    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }

    /**
     * Register Transient Service
     */
    public function transient(string $abstract, string|callable|null $concrete = null): self
    {
        $this->container->transient($abstract, $concrete);
        return $this;
    }

    /**
     * Bind Interface to Implementation
     */
    public function bind(string $interface, string $implementation): self
    {
        $this->container->bind($interface, $implementation);
        return $this;
    }

    /**
     * Set debug mode
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Get debug mode
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set custom error handler
     */
    public function setErrorHandler(callable $handler): self
    {
        $this->errorHandler = $handler;
        return $this;
    }

    /**
     * Get Application Version
     */
    public function version(): string
    {
        try {
            $config = $this->loadConfig('app/Config/app.php');
            return $config['version'] ?? '1.0.0';
        } catch (\Exception) {
            return '1.0.0';
        }
    }

    /**
     * Run Application
     */
    public function run(Request $request): Response
    {
        try {
            return $this->router->handle($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Handle exceptions
     */
    private function handleException(Throwable $e, Request $request): Response
    {
        // Custom Error Handler
        if ($this->errorHandler !== null) {
            $customResponse = ($this->errorHandler)($e, $request);
            if ($customResponse instanceof Response) {
                return $customResponse;
            }
        }

        // Log Error
        error_log(sprintf(
            "Application Error: %s in %s:%d\nStack trace:\n%s",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        // Debug Mode: Show detailed error
        if ($this->debug) {
            return $this->renderDebugError($e, $request);
        }

        // Production Mode: Show generic error
        return $this->renderProductionError($e, $request);
    }

    /**
     * Render Debug Error - Shows detailed error information in development
     */
    private function renderDebugError(Throwable $e, Request $request): Response
    {
        $html = sprintf('
            <!DOCTYPE html>
            <html lang="de">
            <head>
                <title>Error - %s</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
                    .error-container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                    .error-header { background: #dc3545; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                    .error-content { padding: 20px; }
                    .stack-trace { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 14px; }
                    .request-info { margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 4px; }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <div class="error-header">
                        <h1>🚨 Application Error</h1>
                        <p><strong>%s</strong></p>
                        <p>File: %s:%d</p>
                    </div>
                    <div class="error-content">
                        <h3>Stack Trace</h3>
                        <div class="stack-trace">%s</div>
                        
                        <div class="request-info">
                            <h3>Request Information</h3>
                            <p><strong>Method:</strong> %s</p>
                            <p><strong>URI:</strong> %s</p>
                            <p><strong>User Agent:</strong> %s</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>',
            htmlspecialchars(get_class($e)),
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getFile()),
            $e->getLine(),
            htmlspecialchars($e->getTraceAsString()),
            htmlspecialchars($request->getMethod()->value),
            htmlspecialchars($request->getUri()),
            htmlspecialchars($request->getHeader('User-Agent') ?? 'Unknown')
        );

        return new Response(HttpStatus::INTERNAL_SERVER_ERROR, [], $html);
    }

    /**
     * Render Production Error - Shows generic error in production
     */
    private function renderProductionError(Throwable $e, Request $request): Response
    {
        // Try to use template engine if available
        try {
            $responseFactory = $this->get(ResponseFactory::class);
            return $responseFactory->view('errors/500', [
                'message' => 'Ein unerwarteter Fehler ist aufgetreten.',
            ], HttpStatus::INTERNAL_SERVER_ERROR);
        } catch (\Throwable) {
            // Fallback to simple HTML
            $html = '
                <!DOCTYPE html>
                <html lang="de">
                <head>
                    <title>Fehler</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; text-align: center; }
                        .error-container { max-width: 600px; margin: 100px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                        h1 { color: #dc3545; margin-bottom: 20px; }
                        p { color: #6c757d; line-height: 1.6; }
                    </style>
                </head>
                <body>
                    <div class="error-container">
                        <h1>🚨 Fehler</h1>
                        <p>Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.</p>
                        <p>Falls das Problem weiterhin besteht, kontaktieren Sie bitte den Administrator.</p>
                    </div>
                </body>
                </html>';

            return new Response(HttpStatus::INTERNAL_SERVER_ERROR, [], $html);
        }
    }
}