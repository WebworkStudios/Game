<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Database\ConnectionManager;
use Framework\Database\DatabaseServiceProvider;
use Framework\Database\QueryBuilder;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Router;
use Framework\Routing\RouterCache;
use Framework\Security\Csrf;
use Framework\Security\SecurityServiceProvider;
use Framework\Security\Session;
use Framework\Templating\TemplateEngine;
use Framework\Templating\TemplatingServiceProvider;
use Framework\Templating\ViewRenderer;
use Framework\Validation\ValidationServiceProvider;
use Framework\Validation\Validator;
use Framework\Validation\ValidatorFactory;
use RuntimeException;
use Throwable;

/**
 * Application - Bootstrap und Orchestrierung des Frameworks (Database, Security, Validation & Templating)
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
     * Bootstrap der Anwendung
     */
    private function bootstrap(): void
    {
        $this->setupEnvironment();
        $this->registerCoreServices();
        $this->registerDatabaseServices();
        $this->registerSecurityServices();
        $this->registerValidationServices();
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

        // Session (wird später durch SecurityServiceProvider verwaltet)
        // Hier bewusst nicht starten, da SessionMiddleware das übernimmt
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

        // Router registrieren
        $this->container->singleton(Router::class, function (ServiceContainer $container) {
            return new Router(
                container: $container,
                cache: $container->get(RouterCache::class)
            );
        });
    }

    /**
     * Registriert einen Service
     */
    public function singleton(string $abstract, string|callable|null $concrete = null): self
    {
        $this->container->singleton($abstract, $concrete);
        return $this;
    }

    /**
     * Registriert Database-Services
     */
    private function registerDatabaseServices(): void
    {
        $provider = new DatabaseServiceProvider($this->container, $this);
        $provider->register();
    }

    /**
     * Registriert Security-Services
     */
    private function registerSecurityServices(): void
    {
        $provider = new SecurityServiceProvider($this->container, $this);
        $provider->register();
    }

    /**
     * Registriert Validation-Services
     */
    private function registerValidationServices(): void
    {
        $provider = new ValidationServiceProvider($this->container, $this);
        $provider->register();
    }

    /**
     * Registriert Templating-Services
     */
    private function registerTemplatingServices(): void
    {
        $provider = new TemplatingServiceProvider($this->container, $this);
        $provider->register();

        // ViewRenderer registrieren
        $this->container->singleton(ViewRenderer::class, function (ServiceContainer $container) {
            return new ViewRenderer(
                engine: $container->get(TemplateEngine::class)
            );
        });
    }

    /**
     * Setup des Routers
     */
    private function setupRouter(): void
    {
        $this->router = $this->container->get(Router::class);

        // GLOBALE MIDDLEWARE REGISTRIEREN
        $this->router->addGlobalMiddleware(\Framework\Security\SessionMiddleware::class);
        $this->router->addGlobalMiddleware(\Framework\Security\CsrfMiddleware::class);

        // Standard 404 Handler
        $this->router->setNotFoundHandler(function (Request $request) {
            return $this->render404Page($request);
        });

        // Standard 405 Handler
        $this->router->setMethodNotAllowedHandler(function (Request $request) {
            return Response::methodNotAllowed('Method Not Allowed');
        });
    }

    /**
     * Setzt 404-Handler
     */
    public function setNotFoundHandler(callable $handler): self
    {
        $this->router->setNotFoundHandler($handler);
        return $this;
    }

    /**
     * Rendert 404-Seite
     */
    private function render404Page(Request $request): Response
    {
        // Versuche Template zu laden, falls verfügbar
        try {
            return $this->view('errors/404', [
                'path' => $request->getPath(),
                'method' => $request->getMethod()->value,
            ]);
        } catch (Throwable) {
            // Fallback zu HTML
            $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <title>404 - Page Not Found</title>
                <meta charset='utf-8'>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
                    .error { color: #666; }
                    .code { font-size: 4em; font-weight: bold; color: #333; }
                    .message { font-size: 1.2em; margin: 20px 0; }
                    .path { background: #f5f5f5; padding: 10px; border-radius: 5px; margin: 20px auto; max-width: 600px; }
                </style>
            </head>
            <body>
                <div class='error'>
                    <div class='code'>404</div>
                    <div class='message'>Page Not Found</div>
                    <div class='path'>Path: {$request->getPath()}</div>
                    <a href='/'>← Back to Home</a>
                </div>
            </body>
            </html>";

            return Response::notFound($html);
        }
    }

    /**
     * Render template to Response
     */
    public function view(string $template, array $data = []): Response
    {
        return $this->getViewRenderer()->render($template, $data);
    }

    /**
     * Render template
     */
    public function render(string $template, array $data = []): string
    {
        return $this->getTemplateEngine()->render($template, $data);
    }

    /**
     * Holt Template Engine
     */
    public function getTemplateEngine(): TemplateEngine
    {
        return $this->container->get(TemplateEngine::class);
    }

    /**
     * Holt View Renderer
     */
    public function getViewRenderer(): ViewRenderer
    {
        return $this->container->get(ViewRenderer::class);
    }

    /**
     * Startet die Anwendung und verarbeitet Request
     */
    public function run(): void
    {
        try {
            $request = Request::fromGlobals();
            $response = $this->handle($request);
            $response->send();
        } catch (Throwable $e) {
            $this->handleException($e)->send();
        }
    }

    /**
     * Verarbeitet HTTP-Request und gibt Response zurück
     */
    public function handle(Request $request): Response
    {
        try {
            return $this->router->handle($request);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Behandelt Exceptions
     */
    private function handleException(Throwable $e): Response
    {
        // Custom Error Handler
        if ($this->errorHandler !== null) {
            try {
                $response = ($this->errorHandler)($e);
                if ($response instanceof Response) {
                    return $response;
                }
            } catch (Throwable) {
                // Fallback zu Standard-Handler
            }
        }

        // Debug-Modus: Detaillierte Fehlerausgabe
        if ($this->debug) {
            return $this->renderDebugError($e);
        }

        // Production: Generische Fehlerseite
        return $this->renderProductionError($e);
    }

    /**
     * Rendert Debug-Fehlerseite
     */
    private function renderDebugError(Throwable $e): Response
    {
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Error - {$e->getMessage()}</title>
            <meta charset='utf-8'>
            <style>
                body { font-family: monospace; margin: 20px; background: #f8f8f8; }
                .error { background: white; padding: 20px; border-left: 5px solid #e74c3c; }
                .message { font-size: 1.2em; font-weight: bold; color: #e74c3c; margin-bottom: 10px; }
                .file { color: #666; margin-bottom: 20px; }
                .trace { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
                pre { margin: 0; white-space: pre-wrap; }
            </style>
        </head>
        <body>
            <div class='error'>
                <div class='message'>" . get_class($e) . ": {$e->getMessage()}</div>
                <div class='file'>File: {$e->getFile()}:{$e->getLine()}</div>
                <div class='trace'>
                    <strong>Stack Trace:</strong>
                    <pre>{$e->getTraceAsString()}</pre>
                </div>
            </div>
        </body>
        </html>";

        return Response::serverError($html);
    }

    /**
     * Rendert Production-Fehlerseite
     */
    private function renderProductionError(Throwable $e): Response
    {
        // Log error
        error_log("Application Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

        // Versuche Template zu laden
        try {
            return $this->view('errors/500', [
                'message' => 'Something went wrong. Please try again later.',
            ]);
        } catch (Throwable) {
            // Fallback zu HTML
            $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <title>500 - Internal Server Error</title>
                <meta charset='utf-8'>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
                    .error { color: #666; }
                    .code { font-size: 4em; font-weight: bold; color: #e74c3c; }
                    .message { font-size: 1.2em; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='error'>
                    <div class='code'>500</div>
                    <div class='message'>Internal Server Error</div>
                    <p>Something went wrong. Please try again later.</p>
                    <a href='/'>← Back to Home</a>
                </div>
            </body>
            </html>";

            return Response::serverError($html);
        }
    }

    /**
     * Prüft Debug-Modus
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

    /**
     * Holt Service Container
     */
    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }

    /**
     * Holt Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Holt Base Path
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Erstellt neuen QueryBuilder
     */
    public function query(string $connectionName = 'default'): QueryBuilder
    {
        /** @var callable $factory */
        $factory = $this->container->get('query_builder_factory');
        return $factory($connectionName);
    }

    /**
     * Führt Database-Transaktion aus
     */
    public function transaction(callable $callback, string $connectionName = 'default'): mixed
    {
        return $this->getDatabase()->transaction($callback, $connectionName);
    }

    /**
     * Holt Database Connection Manager
     */
    public function getDatabase(): ConnectionManager
    {
        return $this->container->get(ConnectionManager::class);
    }

    /**
     * Holt Session-Service
     */
    public function getSession(): Session
    {
        return $this->container->get(Session::class);
    }

    /**
     * Holt CSRF-Service
     */
    public function getCsrf(): Csrf
    {
        return $this->container->get(Csrf::class);
    }

    /**
     * Validiert Daten und gibt Validator zurück
     */
    public function validate(array $data, array $rules, ?string $connectionName = null): Validator
    {
        return $this->validator($data, $rules, $connectionName)->validate();
    }

    /**
     * Erstellt Validator-Instanz
     */
    public function validator(array $data, array $rules, ?string $connectionName = null): Validator
    {
        /** @var ValidatorFactory $factory */
        $factory = $this->container->get(ValidatorFactory::class);
        return $factory->make($data, $rules, $connectionName);
    }

    /**
     * Validiert Daten oder wirft Exception bei Fehler
     */
    public function validateOrFail(array $data, array $rules, ?string $connectionName = null): array
    {
        return $this->validator($data, $rules, $connectionName)->validateOrFail();
    }

    /**
     * Registriert Transient Service
     */
    public function transient(string $abstract, string|callable|null $concrete = null): self
    {
        $this->container->transient($abstract, $concrete);
        return $this;
    }

    /**
     * Bindet Interface an Implementierung
     */
    public function bind(string $interface, string $implementation): self
    {
        $this->container->bind($interface, $implementation);
        return $this;
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
     * Installiert Framework (erstellt Verzeichnisse und Konfigurationen)
     */
// framework/Core/Application.php

    public function install(): bool
    {
        $success = true;

        // Erstelle Verzeichnisse (bleibt gleich)
        $directories = [
            'storage/cache',
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
        ];

        foreach ($directories as $dir) {
            $fullPath = $this->basePath . '/' . $dir;
            if (!is_dir($fullPath) && !mkdir($fullPath, 0755, true)) {
                echo "Failed to create directory: {$fullPath}\n";
                $success = false;
            }
        }

        // Erstelle alle Konfigurationsdateien
        $configs = [
            'App' => [$this, 'createAppConfig'],
            'Database' => [DatabaseServiceProvider::class, 'publishConfig'],
            'Security' => [SecurityServiceProvider::class, 'publishConfig'],
            'Templating' => [TemplatingServiceProvider::class, 'publishConfig'],
        ];

        foreach ($configs as $name => $callback) {
            if (!call_user_func($callback, $this->basePath)) {
                echo "Failed to create {$name} config\n";
                $success = false;
            } else {
                echo "✅ Created {$name} config\n";
            }
        }

        return $success;
    }

    /**
     * Erstellt App-Konfiguration
     */
    private function createAppConfig(string $basePath): bool
    {
        $configPath = $basePath . '/app/Config/app.php';
        $configDir = dirname($configPath);

        if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
            return false;
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
    
    'name' => 'Football Manager',
    'version' => '1.0.0',
    'debug' => true, // Set to false in production
    'timezone' => 'UTC',
    'locale' => 'en',
    
    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */
    
    'url' => 'http://localhost:8000',
    
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    
    'log' => [
        'level' => 'debug', // debug, info, warning, error
        'path' => 'storage/logs/app.log',
        'max_files' => 7, // Keep 7 days of logs
    ],
];
PHP;

        return file_put_contents($configPath, $content) !== false;
    }
}