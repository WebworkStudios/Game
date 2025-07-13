<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Database\ConnectionManager;
use Framework\Database\DatabaseServiceProvider;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
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

    /** @var callable|null */
    private $notFoundHandler = null;

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

        ServiceRegistry::setContainer($this->container);

        // RouterCache registrieren
        $this->container->singleton(RouterCache::class, function () {
            return new RouterCache(
                cacheFile: $this->basePath . '/storage/cache/routes.php',
                actionsPath: $this->basePath . '/app/Actions'
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
     * Generic Service Access
     */
    public function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
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

        // Globale Middleware registrieren (falls die Methoden existieren)
        if (method_exists($this->router, 'addGlobalMiddleware')) {
            $this->router->addGlobalMiddleware(\Framework\Security\SessionMiddleware::class);
            $this->router->addGlobalMiddleware(\Framework\Security\CsrfMiddleware::class);
        }

        // 404 Handler setzen (falls die Methode existiert)
        if (method_exists($this->router, 'setNotFoundHandler')) {
            $this->router->setNotFoundHandler(function (Request $request) {
                return $this->handleNotFound($request);
            });
        }
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(Request $request): Response
    {
        if ($this->notFoundHandler) {
            $response = ($this->notFoundHandler)($request);
            if ($response instanceof Response) {
                return $response;
            }
        }

        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Route not found',
                'path' => $request->getPath(),
                'method' => $request->getMethod()->value,
            ], HttpStatus::NOT_FOUND);
        }

        return Response::notFound('Page Not Found');
    }

    /**
     * Run Application
     */
    public function run(): void
    {
        $request = Request::fromGlobals();
        $response = $this->handle($request);
        $response->send();
    }

    /**
     * Handle HTTP Request
     */
    public function handle(Request $request): Response
    {
        try {
            return $this->router->handle($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Handle Exceptions
     */
    private function handleException(Throwable $e, Request $request): Response
    {
        // Custom Error Handler
        if ($this->errorHandler) {
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
     * Render Debug Error
     */
    private function renderDebugError(Throwable $e, Request $request): Response
    {
        $html = sprintf('
            <!DOCTYPE html>
            <html lang=de>
            <head>
                <title>Error - %s</title>
                <style>
                    body { font-family: monospace; padding: 20px; background: #f8f8f8; }
                    .error { background: #fff; padding: 20px; border-left: 5px solid #ff0000; }
                    .trace { background: #f0f0f0; padding: 10px; margin-top: 20px; }
                    pre { white-space: pre-wrap; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h1>%s</h1>
                    <p><strong>File:</strong> %s</p>
                    <p><strong>Line:</strong> %d</p>
                    <p><strong>Message:</strong> %s</p>
                </div>
                <div class="trace">
                    <h3>Stack Trace:</h3>
                    <pre>%s</pre>
                </div>
            </body>
            </html>',
            get_class($e),
            get_class($e),
            htmlspecialchars($e->getFile()),
            $e->getLine(),
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getTraceAsString())
        );

        return Response::serverError($html);
    }

    /**
     * Render Production Error
     */
    private function renderProductionError(Throwable $e, Request $request): Response
    {
        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Internal Server Error',
                'message' => 'An unexpected error occurred'
            ], HttpStatus::INTERNAL_SERVER_ERROR);
        }

        return Response::serverError('Internal Server Error');
    }

    /**
     * Check if Debug Mode is enabled
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set Debug Mode
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;

        // Update error display based on debug mode
        ini_set('display_errors', $debug ? '1' : '0');

        return $this;
    }

    /**
     * Set Custom Error Handler
     */
    public function setErrorHandler(?callable $handler): self
    {
        $this->errorHandler = $handler;
        return $this;
    }

    /**
     * Get Custom 404 Handler
     */
    public function getNotFoundHandler(): ?callable
    {
        return $this->notFoundHandler;
    }

    /**
     * Set Custom 404 Handler
     */
    public function setNotFoundHandler(?callable $handler): self
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /**
     * Get Base Path
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get Service Container
     */
    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }

    /**
     * Get Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Database Transaction Helper
     */
    public function transaction(callable $callback, ?string $connectionName = null): mixed
    {
        return $this->getDatabase()->transaction($callback, $connectionName);
    }

    /**
     * Get Database Connection Manager
     */
    public function getDatabase(): ConnectionManager
    {
        return $this->container->get(ConnectionManager::class);
    }

    /**
     * Get CSRF service
     */
    public function getCsrf(): Csrf
    {
        return $this->get('csrf');
    }

    /**
     * Force session start (useful for testing)
     */
    public function startSession(): bool
    {
        try {
            return $this->getSession()->start();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get Session service
     */
    public function getSession(): Session
    {
        return $this->get('session');
    }

    /**
     * Get session status for debugging
     */
    public function getSessionStatus(): array
    {
        try {
            $session = $this->getSession();
            $sessionSecurity = $this->getSessionSecurity();

            return [
                'session' => $session->getStatus(),
                'security' => $sessionSecurity->getSecurityStatus(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
                'session' => ['started' => false],
                'security' => ['enabled' => false],
            ];
        }
    }

    /**
     * Get SessionSecurity service
     */
    public function getSessionSecurity(): SessionSecurity
    {
        return $this->get('session_security');
    }

    /**
     * Get framework status for health checks
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->name(),
            'version' => $this->version(),
            'debug' => $this->debug,
            'installed' => $this->isInstalled(),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'session_status' => $this->hasValidSession(),
            'services' => [
                'database' => $this->container->has(ConnectionManager::class),
                'session' => $this->container->has(Session::class),
                'csrf' => $this->container->has(Csrf::class),
                'templates' => $this->container->has(TemplateEngine::class),
                'validation' => $this->container->has(ValidatorFactory::class),
                'localization' => $this->container->has(Translator::class),
            ],
        ];
    }

    /**
     * Get Application Name
     */
    public function name(): string
    {
        try {
            $config = $this->loadConfig('app/Config/app.php');
            return $config['name'] ?? 'PHP Framework';
        } catch (\Exception) {
            return 'PHP Framework';
        }
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
     * Check if application is installed
     */
    public function isInstalled(): bool
    {
        return file_exists($this->basePath . '/app/Config/app.php') &&
            file_exists($this->basePath . '/app/Config/database.php') &&
            file_exists($this->basePath . '/app/Config/security.php');
    }

    /**
     * Check if session is active and valid
     */
    public function hasValidSession(): bool
    {
        try {
            $session = $this->getSession();
            return $session->isStarted() && !$session->isExpired();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get Template Engine
     */
    public function getTemplateEngine(): TemplateEngine
    {
        return $this->container->get(TemplateEngine::class);
    }

    /**
     * Get View Renderer
     */
    public function getViewRenderer(): ViewRenderer
    {
        return $this->container->get(ViewRenderer::class);
    }

    /**
     * Get Translator
     */
    public function getTranslator(): Translator
    {
        return $this->container->get(Translator::class);
    }

    /**
     * Validate data and return Validator
     */
    public function validate(array $data, array $rules, ?string $connectionName = null): Validator
    {
        return $this->validator($data, $rules, $connectionName)->validate();
    }

    /**
     * Create Validator instance
     */
    public function validator(array $data, array $rules, ?string $connectionName = null): Validator
    {
        /** @var ValidatorFactory $factory */
        $factory = $this->container->get(ValidatorFactory::class);
        return $factory->make($data, $rules, $connectionName);
    }

    /**
     * Validate data or throw exception on failure
     */
    public function validateOrFail(array $data, array $rules, ?string $connectionName = null): array
    {
        return $this->validator($data, $rules, $connectionName)->validateOrFail();
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
     * Install Framework (create directories and configurations)
     */
    public function install(): bool
    {
        $success = true;

        // Create Directories
        $directories = [
            'storage/cache',
            'storage/cache/data',
            'storage/cache/views',
            'storage/logs',
            'storage/sessions',
            'storage/uploads',
            'app/Config',
            'app/Actions',
            'app/Middleware',
            'app/Views',
            'app/Views/layouts',
            'app/Views/components',
            'app/Views/pages',
            'app/Languages',
            'app/Languages/de',
            'app/Languages/en',
            'app/Languages/fr',
            'app/Languages/es',
            'app/Domain',
            'app/Domain/User',
            'app/Domain/User/Entities',
            'app/Domain/User/ValueObjects',
            'app/Domain/User/Repositories',
            'app/Domain/User/Services',
            'app/Repositories',
            'app/Services',
        ];

        foreach ($directories as $dir) {
            $fullPath = $this->basePath . '/' . $dir;
            if (!is_dir($fullPath)) {
                if (!mkdir($fullPath, 0755, true)) {
                    $success = false;
                    error_log("Failed to create directory: {$fullPath}");
                }
            }
        }

        // Create Configuration Files
        $configs = [
            'app' => $this->createAppConfig(),
            'database' => DatabaseServiceProvider::publishConfig($this->basePath),
            'security' => SecurityServiceProvider::publishConfig($this->basePath),
            'templating' => TemplatingServiceProvider::publishConfig($this->basePath),
            'localization' => LocalizationServiceProvider::publishConfig($this->basePath),
        ];

        foreach ($configs as $name => $created) {
            if (!$created) {
                $success = false;
                error_log("Failed to create {$name} configuration");
            }
        }

        return $success;
    }

    /**
     * Create app configuration file
     */
    private function createAppConfig(): bool
    {
        $configPath = $this->basePath . '/app/Config/app.php';

        if (file_exists($configPath)) {
            return true; // Already exists
        }

        $content = <<<'PHP'
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Application Configuration
    |--------------------------------------------------------------------------
    */
    'name' => 'Football Manager Game',
    'version' => '1.0.0',
    'debug' => true, // Set to false in production
    'timezone' => 'Europe/Berlin',
    'locale' => 'de',
    'fallback_locale' => 'en',
    
    /*
    |--------------------------------------------------------------------------
    | Framework Settings
    |--------------------------------------------------------------------------
    */
    'cache_routes' => true,
    'cache_templates' => false, // Set to true in production
    
    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'key' => 'your-32-character-secret-key-here!',
    'cipher' => 'AES-256-CBC',
    
    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'log_level' => 'debug', // emergency, alert, critical, error, warning, notice, info, debug
    'log_path' => 'storage/logs/app.log',
];
PHP;

        return file_put_contents($configPath, $content) !== false;
    }
}