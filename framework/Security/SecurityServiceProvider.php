<?php
declare(strict_types=1);

namespace Framework\Security;

use Framework\Core\Application;
use Framework\Core\ServiceContainer;

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
     * Erstellt Standard-Konfigurationsdatei - FIXED: Spezifische CSRF-Exemptions
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
    | CSRF Protection - FIXED: Specific exemptions only
    |--------------------------------------------------------------------------
    */
    'csrf' => [
        'token_lifetime' => 7200, // 2 hours
        'regenerate_on_login' => true,
        'exempt_routes' => [
            '/api/*',                    // API routes (use different auth)
            '/webhooks/*',               // Webhook routes
            '/test/template-functions',  // Template testing (no sensitive data)
            '/test/validation',          // Validation testing (no sensitive data)
            // NOTE: /test/localization and /test/security have CSRF protection!
        ],
        'require_https' => false, // Set to true in production
        'auto_cleanup' => true,
        'log_violations' => true,
        'strict_mode' => false, // If true, reject requests without tokens
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    */
    'session_security' => [
        'min_regeneration_interval' => 300, // 5 minutes
        'max_login_attempts' => 5,
        'login_attempt_window' => 900, // 15 minutes
        'enable_fingerprinting' => true,
        'fingerprint_components' => [
            'user_agent' => true,
            'accept_language' => true,
            'ip_address' => false, // Disabled for mobile users
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
        $this->registerSessionSecurity();
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
     * Lädt Security-Konfiguration - FIXED: Verwende array_merge statt array_merge_recursive
     */
    private function loadSecurityConfig(): array
    {
        $configPath = $this->app->getBasePath() . '/' . self::DEFAULT_CONFIG_PATH;

        if (!file_exists($configPath)) {
            // Fallback zu Default-Konfiguration
            return $this->getDefaultConfig();
        }

        $config = require $configPath;

        // FIXED: Verwende array_merge statt array_merge_recursive um Arrays zu vermeiden
        return array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Standard-Konfiguration als Fallback - FIXED: Spezifische CSRF-Exemptions
     */
    private function getDefaultConfig(): array
    {
        return [
            'session' => [
                'lifetime' => 7200,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax',
                'gc_maxlifetime' => 7200,
                'gc_probability' => 1,
                'gc_divisor' => 100,
            ],
            'csrf' => [
                'token_lifetime' => 7200,
                'regenerate_on_login' => true,
                'exempt_routes' => [
                    '/api/*',
                    '/webhooks/*',
                    '/test/template-functions',
                    '/test/validation',
                    // NOTE: /test/localization und /test/security sind NICHT exempt!
                ],
                'require_https' => false,
                'auto_cleanup' => true,
                'log_violations' => true,
                'strict_mode' => false,
            ],
            'session_security' => [
                'min_regeneration_interval' => 300,
                'max_login_attempts' => 5,
                'login_attempt_window' => 900,
                'enable_fingerprinting' => true,
                'fingerprint_components' => [
                    'user_agent' => true,
                    'accept_language' => true,
                    'ip_address' => false,
                ],
            ],
        ];
    }

    /**
     * Registriert SessionSecurity-Service als Singleton
     */
    private function registerSessionSecurity(): void
    {
        $this->container->singleton(SessionSecurity::class, function () {
            $session = $this->container->get(Session::class);
            return new SessionSecurity($session);
        });
    }

    /**
     * Registriert CSRF-Service als Singleton
     */
    private function registerCsrf(): void
    {
        $this->container->singleton(Csrf::class, function () {
            $session = $this->container->get(Session::class);
            return new Csrf($session);
        });
    }

    /**
     * Registriert Middleware-Services
     */
    private function registerMiddlewares(): void
    {
        // Session Middleware
        $this->container->singleton(SessionMiddleware::class, function () {
            $session = $this->container->get(Session::class);
            $sessionSecurity = $this->container->get(SessionSecurity::class);
            return new SessionMiddleware($session, $sessionSecurity);
        });

        // CSRF Middleware (Enhanced with SessionSecurity integration)
        $this->container->singleton(CsrfMiddleware::class, function () {
            $csrf = $this->container->get(Csrf::class);
            $routerCache = $this->container->get(\Framework\Routing\RouterCache::class);
            $sessionSecurity = $this->container->get(SessionSecurity::class);
            $config = $this->loadSecurityConfig();

            return new CsrfMiddleware(
                csrf: $csrf,
                routerCache: $routerCache,
                sessionSecurity: $sessionSecurity,
                config: $config['csrf'] ?? []
            );
        });
    }

    /**
     * Bindet Interfaces an Implementierungen
     */
    private function bindInterfaces(): void
    {
        // Hier können später Interfaces gebunden werden
        // z.B. $this->container->bind(SessionInterface::class, Session::class);
    }

    /**
     * Macht Services global verfügbar über Application
     */
    public function boot(): void
    {
        // Session im Application-Container verfügbar machen
        $this->app->singleton('session', fn() => $this->container->get(Session::class));

        // SessionSecurity im Application-Container verfügbar machen
        $this->app->singleton('session_security', fn() => $this->container->get(SessionSecurity::class));

        // CSRF im Application-Container verfügbar machen
        $this->app->singleton('csrf', fn() => $this->container->get(Csrf::class));
    }

    /**
     * Konfiguration für Tests überschreiben
     */
    public function overrideConfigForTesting(array $testConfig): void
    {
        // Re-registriere Services mit Test-Konfiguration
        $this->container->singleton(Session::class, function () use ($testConfig) {
            return new Session($testConfig['session'] ?? []);
        });

        $this->container->singleton(SessionSecurity::class, function () {
            $session = $this->container->get(Session::class);
            return new SessionSecurity($session);
        });

        $this->container->singleton(Csrf::class, function () {
            $session = $this->container->get(Session::class);
            return new Csrf($session);
        });

        // Re-registriere Middlewares mit neuen Services
        $this->container->singleton(SessionMiddleware::class, function () {
            $session = $this->container->get(Session::class);
            $sessionSecurity = $this->container->get(SessionSecurity::class);
            return new SessionMiddleware($session, $sessionSecurity);
        });

        $this->container->singleton(CsrfMiddleware::class, function () use ($testConfig) {
            $csrf = $this->container->get(Csrf::class);
            $routerCache = $this->container->get(\Framework\Routing\RouterCache::class);
            $sessionSecurity = $this->container->get(SessionSecurity::class);

            return new CsrfMiddleware(
                csrf: $csrf,
                routerCache: $routerCache,
                sessionSecurity: $sessionSecurity,
                config: $testConfig['csrf'] ?? []
            );
        });
    }

    /**
     * Gets all security-related services for external access
     */
    public function getSecurityServices(): array
    {
        return [
            'session' => $this->getSessionService(),
            'session_security' => $this->getSessionSecurityService(),
            'csrf' => $this->getCsrfService(),
        ];
    }

    /**
     * Hilfsmethoden für Application-Klasse
     */
    public function getSessionService(): Session
    {
        return $this->container->get(Session::class);
    }

    public function getSessionSecurityService(): SessionSecurity
    {
        return $this->container->get(SessionSecurity::class);
    }

    public function getCsrfService(): Csrf
    {
        return $this->container->get(Csrf::class);
    }

    /**
     * Validates and prepares production configuration
     */
    public function prepareProductionConfig(): array
    {
        $config = $this->loadSecurityConfig();

        // Force secure settings in production
        $config['session']['secure'] = true;
        $config['session']['httponly'] = true;
        $config['session']['samesite'] = 'Strict';
        $config['csrf']['require_https'] = true;
        $config['csrf']['strict_mode'] = true;

        return $config;
    }

    /**
     * Validiert Security-Konfiguration
     */
    private function validateConfig(array $config): bool
    {
        // Session-Konfiguration validieren
        if (isset($config['session']['lifetime']) && $config['session']['lifetime'] < 0) {
            throw new \InvalidArgumentException('Session lifetime must be positive');
        }

        // CSRF-Konfiguration validieren
        if (isset($config['csrf']['token_lifetime']) && $config['csrf']['token_lifetime'] < 0) {
            throw new \InvalidArgumentException('CSRF token lifetime must be positive');
        }

        // SessionSecurity-Konfiguration validieren
        if (isset($config['session_security']['min_regeneration_interval']) &&
            $config['session_security']['min_regeneration_interval'] < 0) {
            throw new \InvalidArgumentException('Min regeneration interval must be positive');
        }

        if (isset($config['session_security']['max_login_attempts']) &&
            $config['session_security']['max_login_attempts'] < 1) {
            throw new \InvalidArgumentException('Max login attempts must be at least 1');
        }

        if (isset($config['csrf']['exempt_routes']) &&
            !is_array($config['csrf']['exempt_routes'])) {
            throw new \InvalidArgumentException('CSRF exempt_routes must be an array');
        }

        if (isset($config['csrf']['require_https']) &&
            !is_bool($config['csrf']['require_https'])) {
            throw new \InvalidArgumentException('CSRF require_https must be boolean');
        }

        return true;
    }
}