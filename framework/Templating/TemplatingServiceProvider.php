<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Core\Application;
use Framework\Core\ServiceContainer;

/**
 * Templating Service Provider - Registriert alle Template-Services mit XSS-Schutz
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
     * Registriert alle Templating Services
     */
    public function register(): void
    {
        $this->registerTemplateEngine();
        $this->registerViewRenderer();
    }

    /**
     * Registriert TemplateEngine als Singleton mit XSS-Schutz
     */
    private function registerTemplateEngine(): void
    {
        $this->container->singleton(TemplateEngine::class, function () {
            $config = $this->loadTemplatingConfig();

            // Create template cache
            $cacheConfig = $config['cache'] ?? [];
            $cacheDir = $this->app->getBasePath() . '/' . ($cacheConfig['path'] ?? 'storage/cache/views');
            $cacheEnabled = $cacheConfig['enabled'] ?? !$this->app->isDebug();

            $cache = new TemplateCache($cacheDir, $cacheEnabled);

            // *** XSS-SCHUTZ: Auto-Escape Configuration ***
            $autoEscape = $config['auto_escape'] ?? true; // Default: XSS-Schutz aktiviert

            $engine = new TemplateEngine([], $cache, $autoEscape);

            // Add configured template paths
            foreach ($config['paths'] as $path) {
                $fullPath = $this->app->getBasePath() . '/' . ltrim($path, '/');
                $engine->addPath($fullPath);
            }

            return $engine;
        });
    }

    /**
     * Lädt Templating-Konfiguration
     */
    private function loadTemplatingConfig(): array
    {
        $configPath = $this->app->getBasePath() . '/' . self::DEFAULT_CONFIG_PATH;

        if (!file_exists($configPath)) {
            // Create default config with XSS protection
            self::publishConfig($this->app->getBasePath());
        }

        $config = require $configPath;
        return is_array($config) ? $config : $this->getDefaultConfig();
    }

    /**
     * Erstellt Standard-Konfigurationsdatei mit XSS-Schutz
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
     * Registriert ViewRenderer als Singleton
     */
    private function registerViewRenderer(): void
    {
        $this->container->singleton(ViewRenderer::class, function (ServiceContainer $container) {
            return new ViewRenderer(
                engine: $container->get(TemplateEngine::class)
            );
        });
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

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return $this->getTemplateEngine()->getCacheStats();
    }
}