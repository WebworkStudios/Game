<?php
declare(strict_types=1);

namespace Framework\Security;

use Framework\Core\Application;
use Framework\Core\ServiceContainer;
use Framework\Routing\RouterCache;

/**
 * Security Service Provider - Registriert Security-Services im Framework
 */
class SecurityServiceProvider
{
    private const string DEFAULT_CONFIG_PATH = 'app/Config/security.php';

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly Application      $app,
    )
    {
    }

    /**
     * Erstellt Standard-Konfigurationsdatei
     */
    public static function publishConfig(string $basePath): bool
    {
        $configPath = $basePath . '/' . self::DEFAULT_CONFIG_PATH;
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
    | Session Configuration
    |--------------------------------------------------------------------------
    */
    'session' => [
        'lifetime' => env('SESSION_LIFETIME', 7200), // 2 Stunden
        'path' => env('SESSION_PATH', '/'),
        'domain' => env('SESSION_DOMAIN', ''),
        'secure' => env('SESSION_SECURE', false), // In Production: true
        'httponly' => env('SESSION_HTTPONLY', true),
        'samesite' => env('SESSION_SAMESITE', 'Lax'), // Lax, Strict, None
        'gc_maxlifetime' => env('SESSION_GC_MAXLIFETIME', 7200),
        'gc_probability' => env('SESSION_GC_PROBABILITY', 1),
        'gc_divisor' => env('SESSION_GC_DIVISOR', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */
    'csrf' => [
        'token_lifetime' => env('CSRF_TOKEN_LIFETIME', 7200), // 2 Stunden
        'regenerate_on_login' => env('CSRF_REGENERATE_ON_LOGIN', true),
        'exclude_routes' => [
            '/api/*',      // API-Routen ausschließen
            '/webhooks/*', // Webhook-Routen ausschließen
        ],
    ],
];

/**
 * Helper function für Environment Variables
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // Convert string booleans
    return match(strtolower($value)) {
        'true', '1', 'on', 'yes' => true,
        'false', '0', 'off', 'no' => false,
        default => $value,
    };
}
PHP;

        return file_put_contents($configPath, $content) !== false;
    }

    /**
     * Registriert alle Security Services
     */
    public function register(): void
    {
        $this->registerSession();
        $this->registerCsrf();
        $this->registerMiddlewares();
        $this->bindInterfaces();
    }

    /**
     * Registriert Session-Service als Singleton
     */
    private function registerSession(): void
    {
        $this->container->singleton(Session::class, function () {
            $config = $this->loadSecurityConfig();
            return new Session($config['session'] ?? []);
        });
    }

    /**
     * Lädt Security-Konfiguration
     */
    private function loadSecurityConfig(): array
    {
        $configPath = $this->app->getBasePath() . '/' . self::DEFAULT_CONFIG_PATH;

        if (!file_exists($configPath)) {
            // Default-Konfiguration zurückgeben wenn keine Config-Datei existiert
            return $this->getDefaultConfig();
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new \InvalidArgumentException('Security config must return array');
        }

        return array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Standard-Konfiguration
     */
    private function getDefaultConfig(): array
    {
        return [
            'session' => [
                'lifetime' => 7200, // 2 Stunden
                'path' => '/',
                'domain' => '',
                'secure' => $_SERVER['HTTPS'] ?? false, // Auto-detect HTTPS
                'httponly' => true,
                'samesite' => 'Lax',
                'gc_maxlifetime' => 7200,
                'gc_probability' => 1,
                'gc_divisor' => 100,
            ],
            'csrf' => [
                'token_lifetime' => 7200, // 2 Stunden
                'regenerate_on_login' => true,
            ],
        ];
    }

    /**
     * Registriert CSRF-Service als Singleton
     */
    private function registerCsrf(): void
    {
        $this->container->singleton(Csrf::class, function (ServiceContainer $container) {
            return new Csrf(
                session: $container->get(Session::class)
            );
        });
    }

    /**
     * Registriert Middlewares
     */
    private function registerMiddlewares(): void
    {
        $this->container->transient(SessionMiddleware::class, function (ServiceContainer $container) {
            return new SessionMiddleware(
                session: $container->get(Session::class)
            );
        });

        $this->container->transient(CsrfMiddleware::class, function (ServiceContainer $container) {
            return new CsrfMiddleware(
                csrf: $container->get(Csrf::class),
                routerCache: $container->get(RouterCache::class) // ← WICHTIG!
            );
        });
    }

    /**
     * Bindet Interfaces (für zukünftige Erweiterungen)
     */
    private function bindInterfaces(): void
    {
        // Placeholder für Security-Interfaces
        // $this->container->bind(SessionInterface::class, Session::class);
        // $this->container->bind(CsrfInterface::class, Csrf::class);
    }
}