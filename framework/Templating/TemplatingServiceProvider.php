<?php
// framework/Templating/TemplatingServiceProvider.php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Core\Application;
use Framework\Core\ServiceContainer;

/**
 * Templating Service Provider
 */
class TemplatingServiceProvider
{
    private const string DEFAULT_CONFIG_PATH = 'app/Config/templating.php';

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly Application $app,
    ) {}

    public function register(): void
    {
        $this->registerTemplateEngine();
    }

    private function registerTemplateEngine(): void
    {
        $this->container->singleton(TemplateEngine::class, function () {
            $config = $this->loadTemplatingConfig();

            $engine = new TemplateEngine();

            // Add configured template paths
            foreach ($config['paths'] as $path) {
                $fullPath = $this->app->getBasePath() . '/' . ltrim($path, '/');
                $engine->addPath($fullPath);
            }

            return $engine;
        });
    }

    private function loadTemplatingConfig(): array
    {
        $configPath = $this->app->getBasePath() . '/' . self::DEFAULT_CONFIG_PATH;

        if (!file_exists($configPath)) {
            // Create default config
            self::publishConfig($this->app->getBasePath());
        }

        $config = require $configPath;
        return is_array($config) ? $config : $this->getDefaultConfig();
    }

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
    */
    'paths' => [
        'app/Views',
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Settings
    |--------------------------------------------------------------------------
    */
    'auto_escape' => true,
    'debug' => false,
];
PHP;

        return file_put_contents($configPath, $content) !== false;
    }

    private function getDefaultConfig(): array
    {
        return [
            'paths' => ['app/Views'],
            'auto_escape' => true,
            'debug' => false,
        ];
    }
}