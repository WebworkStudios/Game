<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Core\Application;
use Framework\Core\ServiceContainer;
use Framework\Localization\Translator;
use Framework\Security\Csrf;

/**
 * Templating Service Provider - Registriert Template Services im Framework
 */
class TemplatingServiceProvider
{
    private const string DEFAULT_CONFIG_PATH = 'app/Config/templating.php';

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
    | Template Paths
    |--------------------------------------------------------------------------
    | 
    | Define where your template files are located. Multiple paths can be
    | specified and will be searched in order.
    |
    */
    'paths' => [
        'app/Views',
    ],

    /*
    |--------------------------------------------------------------------------
    | XSS Protection (Auto-Escape)
    |--------------------------------------------------------------------------
    | 
    | When enabled, all template variables {{ $var }} will be automatically
    | HTML-escaped to prevent XSS attacks. Use {{ $var|raw }} to disable
    | escaping for trusted content only.
    |
    | SECURITY: Keep this enabled in production!
    |
    */
    'auto_escape' => true,

    /*
    |--------------------------------------------------------------------------
    | Template Cache
    |--------------------------------------------------------------------------
    | 
    | Template caching improves performance by storing compiled templates.
    | In development, you may want to disable caching for instant changes.
    |
    */
    'cache' => [
        'enabled' => true,
        'path' => 'storage/cache/views',
        'auto_reload' => true, // Check for file changes in development
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Settings
    |--------------------------------------------------------------------------
    | 
    | Enable debug mode to get detailed error messages and template info.
    | Should be disabled in production.
    |
    */
    'debug' => false,

    /*
    |--------------------------------------------------------------------------
    | Template File Extension
    |--------------------------------------------------------------------------
    | 
    | Default file extension for template files.
    |
    */
    'extension' => '.html',

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    | 
    | Additional security headers for template responses.
    |
    */
    'security_headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '1; mode=block',
    ],
];
PHP;

        return file_put_contents($configPath, $content) !== false;
    }

    /**
     * Registriert alle Templating Services
     */
    public function register(): void
    {
        $this->registerTemplateEngine();
        $this->registerFilterManager();
        $this->registerViewRenderer();
        $this->registerTemplateCache();
        $this->bindInterfaces();
    }

    /**
     * Registriert Template Engine als Singleton
     */
    private function registerTemplateEngine(): void
    {
        $this->container->singleton(TemplateEngine::class, function (ServiceContainer $container) {
            $config = $this->getConfig();

            // Template Cache
            $cache = null;
            if ($config['cache']['enabled'] ?? false) {
                $cache = $container->get(TemplateCache::class);
            }

            // Konvertiere relative Pfade zu absoluten Pfaden
            $templatePaths = [];
            foreach ($config['paths'] ?? ['app/Views'] as $path) {
                $templatePaths[] = $this->app->getBasePath() . '/' . ltrim($path, '/');
            }

            return new TemplateEngine(
                templatePaths: $templatePaths,
                cache: $cache,
                autoEscape: $config['auto_escape'] ?? true
            );
        });
    }

    /**
     * Holt Konfiguration mit Fallback
     */
    private function getConfig(): array
    {
        try {
            return $this->app->loadConfig(self::DEFAULT_CONFIG_PATH);
        } catch (\Exception) {
            return $this->getDefaultConfig();
        }
    }

    /**
     * Standard-Konfiguration mit XSS-Schutz
     */
    private function getDefaultConfig(): array
    {
        return [
            'paths' => ['app/Views'],
            'auto_escape' => true, // XSS-Schutz standardmäßig aktiviert
            'cache' => [
                'enabled' => !$this->app->isDebug(),
                'path' => 'storage/cache/views',
                'auto_reload' => true,
            ],
            'debug' => $this->app->isDebug(),
            'extension' => '.html',
            'security_headers' => [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-XSS-Protection' => '1; mode=block',
            ],
        ];
    }

    /**
     * Registriert Filter Manager als Singleton
     */
    private function registerFilterManager(): void
    {
        $this->container->singleton(FilterManager::class, function (ServiceContainer $container) {
            // Try to get Translator, but don't fail if not available
            $translator = null;
            try {
                $translator = $container->get(Translator::class);
            } catch (\Throwable) {
                // Translator not available - FilterManager will work without it
            }

            return new FilterManager($translator);
        });
    }

    /**
     * Registriert ViewRenderer als Singleton mit proper DI
     */
    private function registerViewRenderer(): void
    {
        $this->container->singleton(ViewRenderer::class, function (ServiceContainer $container) {
            return new ViewRenderer(
                engine: $container->get(TemplateEngine::class),
                translator: $container->get(Translator::class),
                csrf: $container->get(Csrf::class)
            );
        });
    }

    /**
     * Registriert Template Cache als Singleton
     */
    private function registerTemplateCache(): void
    {
        $this->container->singleton(TemplateCache::class, function () {
            $config = $this->getConfig();

            $cachePath = $this->app->basePath($config['cache']['path'] ?? 'storage/cache/views');
            $enabled = $config['cache']['enabled'] ?? !$this->app->isDebug();

            return new TemplateCache($cachePath, $enabled);
        });
    }

    /**
     * Bindet Interfaces (für zukünftige Erweiterungen)
     */
    private function bindInterfaces(): void
    {
        // Hier können später Interfaces gebunden werden
        // z.B. $this->container->bind(TemplateEngineInterface::class, TemplateEngine::class);
    }

    /**
     * Holt View Renderer aus Container (Helper)
     */
    public function getViewRenderer(): ViewRenderer
    {
        return $this->container->get(ViewRenderer::class);
    }

    /**
     * Clear template cache (for development)
     */
    public function clearCache(): int
    {
        return $this->getTemplateEngine()->clearCache();
    }

    /**
     * Holt Template Engine aus Container (Helper)
     */
    public function getTemplateEngine(): TemplateEngine
    {
        return $this->container->get(TemplateEngine::class);
    }
}