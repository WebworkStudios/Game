<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Database\DatabaseServiceProvider;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Localization\LocalizationServiceProvider;
use Framework\Routing\Router;
use Framework\Routing\RouterCache;
use Framework\Security\SecurityServiceProvider;
use Framework\Templating\TemplateEngine;
use Framework\Templating\TemplatingServiceProvider;
use Framework\Templating\ViewRenderer;
use Framework\Validation\ValidationServiceProvider;
use Framework\Validation\Validator;
use Framework\Validation\ValidatorFactory;
use Framework\Validation\ValidationFailedException;
use Framework\Validation\MessageBag;
use RuntimeException;
use Throwable;

/**
 * Application - Bootstrap und Orchestrierung des Frameworks
 *
 * Aktualisierte Version mit migrierten Service Providern und ConfigManager.
 * Behält alle bestehenden Methoden und Funktionalitäten.
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
        $this->registerSecurityServices();
        $this->registerDatabaseServices();
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

        // ConfigManager als erstes registrieren (wird von anderen Services benötigt)
        $this->container->singleton(ConfigManager::class, function () {
            return new ConfigManager($this->basePath);
        });

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
     * Registriert Security Services
     */
    private function registerSecurityServices(): void
    {
        $provider = new SecurityServiceProvider($this->container, $this);
        $provider->register();
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
        if ($this->errorHandler) {
            $handler = $this->errorHandler;
            $customResponse = $handler($e, $request);

            if ($customResponse instanceof Response) {
                return $customResponse;
            }
        }

        // Default Error Response
        $message = $this->debug ? $e->getMessage() : 'Internal Server Error';

        return new Response(HttpStatus::INTERNAL_SERVER_ERROR, [], $message);
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
     * Register Transient Service
     */
    public function transient(string $abstract, callable|string|null $concrete = null): self
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
     * Generic Service Access
     */
    public function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    /**
     * Get Container for DI
     */
    public function getContainer(): ServiceContainer
    {
        return $this->container;
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
     * Validate data with custom messages support
     */
    public function validate(array $data, array $rules, array $customMessages = [], ?string $connectionName = null): Validator
    {
        $validatorFactory = $this->container->get(ValidatorFactory::class);
        return $validatorFactory->make($data, $rules, $customMessages, $connectionName);
    }

    /**
     * Validate data and throw exception on failure with custom messages
     */
    public function validateOrFail(array $data, array $rules, array $customMessages = [], ?string $connectionName = null): array
    {
        $validator = $this->validate($data, $rules, $customMessages, $connectionName);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors());
        }

        return $validator->validated();
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
     * Run the application
     */
    public function run(): void
    {
        $request = Request::fromGlobals();
        $response = $this->handleRequest($request);
        $response->send();
    }
}