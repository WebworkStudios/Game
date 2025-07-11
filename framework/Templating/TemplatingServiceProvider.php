<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Core\Application;
use Framework\Core\ServiceContainer;
use Framework\Templating\Cache\TemplateCache;
use Framework\Templating\Compiler\TemplateCompiler;
use Framework\Templating\Parser\TemplateParser;
use InvalidArgumentException;

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
    | Template Engine Configuration
    |--------------------------------------------------------------------------
    */
    
    'debug' => false, // Set to true for development
    
    'cache' => [
        'enabled' => true,
        'check_interval' => 60, // seconds
        'path' => 'storage/cache/views',
    ],
    
    'paths' => [
        '' => 'app/Views', // Default namespace
        'layouts' => 'app/Views/layouts',
        'components' => 'app/Views/components',
        'emails' => 'app/Views/emails',
    ],
    
    'globals' => [
        'app_name' => 'Football Manager',
        'app_version' => '1.0.0',
        'current_year' => date('Y'),
    ],
    
    'file_extension' => '.html',
];
PHP;

        return file_put_contents($configPath, $content) !== false;
    }

    /**
     * Registriert alle Templating Services
     */
    public function register(): void
    {
        $this->registerParser();
        $this->registerCompiler();
        $this->registerCache();
        $this->registerEngine();
        $this->bindInterfaces();
    }

    /**
     * Registriert Template Parser
     */
    private function registerParser(): void
    {
        $this->container->singleton(TemplateParser::class, function () {
            return new TemplateParser();
        });
    }

    /**
     * Registriert Template Compiler
     */
    private function registerCompiler(): void
    {
        $this->container->singleton(TemplateCompiler::class, function (ServiceContainer $container) {
            return new TemplateCompiler(
                parser: $container->get(TemplateParser::class)
            );
        });
    }

    /**
     * Registriert Template Cache
     */
    private function registerCache(): void
    {
        $this->container->singleton(TemplateCache::class, function (ServiceContainer $container) {
            $config = $this->loadTemplatingConfig();

            return new TemplateCache(
                compiler: $container->get(TemplateCompiler::class),
                cacheDir: $this->app->getBasePath() . '/' . $config['cache']['path'],
                debug: $config['debug'],
                checkInterval: $config['cache']['check_interval']
            );
        });
    }

    /**
     * Lädt Templating-Konfiguration
     */
    private function loadTemplatingConfig(): array
    {
        $configPath = $this->app->getBasePath() . '/' . self::DEFAULT_CONFIG_PATH;

        if (!file_exists($configPath)) {
            throw new InvalidArgumentException("Templating config not found: {$configPath}");
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new InvalidArgumentException('Templating config must return array');
        }

        return $config; // ← Kein Merge mit Defaults mehr
    }

    /**
     * Registriert Template Engine
     */
    private function registerEngine(): void
    {
        $this->container->singleton(TemplateEngine::class, function (ServiceContainer $container) {
            $config = $this->loadTemplatingConfig();

            $engine = new TemplateEngine(
                cache: $container->get(TemplateCache::class),
                defaultPath: '' // Wird über addPath() gesetzt
            );

            // Add template paths
            foreach ($config['paths'] as $namespace => $path) {
                $fullPath = $this->app->getBasePath() . '/' . $path;
                $engine->addPath($fullPath, $namespace);
            }

            // Add global variables
            foreach ($config['globals'] as $name => $value) {
                $engine->addGlobal($name, $value);
            }

            // Register translation functions if localization is available
            $this->registerTranslationFunctions($engine, $container);

            return $engine;
        });
    }

    /**
     * Registriert Translation-Funktionen im Template Engine
     */
    private function registerTranslationFunctions(TemplateEngine $engine, ServiceContainer $container): void
    {
        // Check if localization services are available
        if (!$container->has(\Framework\Localization\Translator::class)) {
            return; // Localization not registered yet
        }

        try {
            // Register translation helper functions
            $engine->addGlobal('t', function (string $key, array $parameters = []) use ($container): string {
                return $this->translate($key, $parameters, $container);
            });

            $engine->addGlobal('t_plural', function (string $key, int $count, array $parameters = []) use ($container): string {
                return $this->translatePlural($key, $count, $parameters, $container);
            });

            $engine->addGlobal('locale', function () use ($container): string {
                return $this->getCurrentLocale($container);
            });

            $engine->addGlobal('locales', function () use ($container): array {
                return $this->getSupportedLocales($container);
            });

        } catch (\Throwable) {
            // Silently fail if localization services are not available
            // Templates will work without translation functions
        }
    }

    /**
     * Helper: Translate function for templates
     */
    private function translate(string $key, array $parameters, ServiceContainer $container): string
    {
        try {
            $translator = $container->get(\Framework\Localization\Translator::class);
            return $translator->translate($key, $parameters);
        } catch (\Throwable) {
            return $key; // Fallback to key if translation fails
        }
    }

    /**
     * Helper: Translate plural function for templates
     */
    private function translatePlural(string $key, int $count, array $parameters, ServiceContainer $container): string
    {
        try {
            $translator = $container->get(\Framework\Localization\Translator::class);
            return $translator->translatePlural($key, $count, $parameters);
        } catch (\Throwable) {
            return $key; // Fallback to key if translation fails
        }
    }

    /**
     * Helper: Get current locale
     */
    private function getCurrentLocale(ServiceContainer $container): string
    {
        try {
            $translator = $container->get(\Framework\Localization\Translator::class);
            return $translator->getLocale();
        } catch (\Throwable) {
            return 'de'; // Fallback to default
        }
    }

    /**
     * Helper: Get supported locales
     */
    private function getSupportedLocales(ServiceContainer $container): array
    {
        try {
            $translator = $container->get(\Framework\Localization\Translator::class);
            return $translator->getSupportedLocales();
        } catch (\Throwable) {
            return ['de', 'en', 'fr', 'es']; // Fallback to defaults
        }
    }

    /**
     * Bindet Interfaces (für zukünftige Erweiterungen)
     */
    private function bindInterfaces(): void
    {
        // Placeholder für Template-Interfaces
        // $this->container->bind(TemplateEngineInterface::class, TemplateEngine::class);
    }
}