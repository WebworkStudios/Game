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
        'lifetime' => 7200, // 2 hours
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax', // Lax, Strict, None
        'gc_maxlifetime' => 7200,
        'gc_probability' => 1,
        'gc_divisor' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */
    'csrf' => [
        'token_lifetime' => 7200, // 2 hours
        'regenerate_on_login' => true,
        'exclude_routes' => [
            '/api/*',      // API routes (use different auth)
            '/webhooks/*', // Webhook routes
        ],
    ],
];
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