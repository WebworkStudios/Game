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

            return $engine;
        });
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