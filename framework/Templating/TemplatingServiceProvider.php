<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Assets\JavaScriptAssetManager;
use Framework\Cache\CacheManager;
use Framework\Core\AbstractServiceProvider;
use Framework\Core\ConfigManager;
use Framework\Core\ConfigValidation;
use Framework\Localization\Translator;
use Framework\Security\Csrf;
use Framework\Templating\Services\AssetIntegrationManager;
use Framework\Templating\Services\TemplateConfigManager;
use Framework\Templating\Services\TemplateDataInjector;
use Framework\Templating\Services\TemplateErrorHandler;
use Framework\Templating\Filters\FilterExecutor;
use Framework\Templating\Filters\FilterRegistry;
use Framework\Templating\Parsing\ControlFlowParser;
use Framework\Templating\Parsing\TemplateParser;
use Framework\Templating\Parsing\TemplatePathResolver;
use Framework\Templating\Parsing\TemplateTokenizer;
use Framework\Templating\Rendering\TemplateRenderer;
use Framework\Templating\Rendering\TemplateVariableResolver;

/**
 * TemplatingServiceProvider - KRITISCHE FIXES für ConfigManager und FilterManager
 */
class TemplatingServiceProvider extends AbstractServiceProvider
{
    use ConfigValidation;

    protected function validateDependencies(): void
    {
        $this->ensureConfigExists('templating');
        $this->safeValidateTemplateDirectories();
        $this->safeValidateCacheDirectory();
    }

    protected function registerServices(): void
    {
        // Reihenfolge ist wichtig für Dependencies
        $this->registerSupportServices();
        $this->registerParsingServices();
        $this->registerRenderingServices();
        $this->registerCoordinationServices();
        $this->registerSpecializedServices();
        $this->registerIntegrationServices();
    }

    // ===================================================================
    // SUPPORT SERVICES - FIXED
    // ===================================================================

    private function registerSupportServices(): void
    {
        $this->registerTemplateCache();
        $this->registerFilterServices();
    }

    /**
     * ROBUST: Template Cache mit Fallback
     */
    private function registerTemplateCache(): void
    {
        $this->singleton(TemplateCache::class, function () {
            try {
                $config = $this->loadAndValidateConfig('templating');
                $cacheDir = $this->basePath($config['cache']['path'] ?? 'storage/cache/views');
                $enabled = $config['cache']['enabled'] ?? false; // Default: disabled

                return TemplateCache::create($cacheDir, $enabled);
            } catch (\Throwable $e) {
                error_log("Template cache initialization failed: " . $e->getMessage());
                return TemplateCache::createDisabled();
            }
        });
    }

    /**
     * FIXED: Filter Services ohne Dependencies auf ConfigManager/Translator
     */
    private function registerFilterServices(): void
    {
        // FilterRegistry - Basis
        $this->singleton(FilterRegistry::class, function () {
            return new FilterRegistry();
        });

        // FilterExecutor
        $this->singleton(FilterExecutor::class, function () {
            return new FilterExecutor($this->container->get(FilterRegistry::class));
        });

        // FilterManager - OHNE Translator-Dependency
        $this->singleton(FilterManager::class, function () {
            // KRITISCHER FIX: Kein Translator bei der Initialisierung
            return new FilterManager(null);
        });
    }

    // ===================================================================
    // PARSING SERVICES - SIMPLIFIED
    // ===================================================================

    private function registerParsingServices(): void
    {
        $this->registerTemplatePathResolver();
        $this->registerTemplateTokenizer();
        $this->registerControlFlowParser();
        $this->registerTemplateParser();
    }

    /**
     * SIMPLIFIED: TemplatePathResolver ohne ConfigManager-Dependencies
     */
    private function registerTemplatePathResolver(): void
    {
        $this->singleton(TemplatePathResolver::class, function () {
            try {
                $config = $this->loadAndValidateConfig('templating');
                $templatePaths = [];

                foreach ($config['paths'] ?? ['app/Views'] as $path) {
                    $templatePaths[] = $this->basePath(ltrim($path, '/'));
                }

                return new TemplatePathResolver($templatePaths);
            } catch (\Throwable $e) {
                error_log("TemplatePathResolver creation failed: " . $e->getMessage());
                // Emergency fallback
                return new TemplatePathResolver([$this->basePath('app/Views')]);
            }
        });
    }

    private function registerTemplateTokenizer(): void
    {
        $this->singleton(TemplateTokenizer::class, function () {
            return new TemplateTokenizer();
        });
    }

    private function registerControlFlowParser(): void
    {
        $this->singleton(ControlFlowParser::class, function () {
            return new ControlFlowParser();
        });
    }

    private function registerTemplateParser(): void
    {
        $this->singleton(TemplateParser::class, function () {
            return new TemplateParser(
                $this->container->get(TemplateTokenizer::class),
                $this->container->get(ControlFlowParser::class),
                $this->container->get(TemplatePathResolver::class)
            );
        });
    }

    // ===================================================================
    // RENDERING SERVICES
    // ===================================================================

    private function registerRenderingServices(): void
    {
        $this->registerTemplateVariableResolver();
        $this->registerTemplateRenderer();
    }

    private function registerTemplateVariableResolver(): void
    {
        $this->singleton(TemplateVariableResolver::class, function () {
            return new TemplateVariableResolver();
        });
    }

    private function registerTemplateRenderer(): void
    {
        $this->singleton(TemplateRenderer::class, function () {
            return new TemplateRenderer(
                $this->container->get(TemplateVariableResolver::class),
                $this->container->get(FilterManager::class), // ECHTEN FilterManager verwenden
                $this->container->get(TemplatePathResolver::class)
            );
        });
    }

    // ===================================================================
    // COORDINATION SERVICES - FIXED
    // ===================================================================

    private function registerCoordinationServices(): void
    {
        $this->registerTemplateEngine();
    }

    /**
     * CRITICAL FIX: TemplateEngine mit echtem FilterManager
     */
    private function registerTemplateEngine(): void
    {
        $this->singleton(TemplateEngine::class, function () {
            return new TemplateEngine(
                $this->container->get(TemplatePathResolver::class),
                $this->container->get(TemplateCache::class),
                $this->container->get(FilterManager::class) // ECHTEN FilterManager holen
            );
        });
    }

    // ===================================================================
    // SPECIALIZED SERVICES - OPTIONAL
    // ===================================================================

    private function registerSpecializedServices(): void
    {
        // Nur registrieren wenn Klassen existieren
        if (class_exists(TemplateConfigManager::class)) {
            $this->registerTemplateConfigManager();
        }

        if (class_exists(TemplateDataInjector::class)) {
            $this->registerTemplateDataInjector();
        }

        if (class_exists(TemplateErrorHandler::class)) {
            $this->registerTemplateErrorHandler();
        }
    }

    private function registerTemplateConfigManager(): void
    {
        $this->singleton(TemplateConfigManager::class, function () {
            // SIMPLIFIED: Ohne ConfigManager Dependencies
            return new class {
                public function isDebugMode(): bool {
                    return true; // Default debug mode
                }
                public function getConfig(string $key, mixed $default = null): mixed {
                    return $default;
                }
            };
        });
    }

    private function registerTemplateDataInjector(): void
    {
        $this->singleton(TemplateDataInjector::class, function () {
            $csrf = $this->container->has(Csrf::class)
                ? $this->container->get(Csrf::class)
                : null;

            return new class($csrf) {
                public function __construct(private $csrf) {}

                public function injectFrameworkServices(array $data): array {
                    if ($this->csrf) {
                        try {
                            $data['csrf_token'] = $this->csrf->generateToken();
                        } catch (\Throwable $e) {
                            $data['csrf_token'] = '';
                        }
                    }
                    return $data;
                }
            };
        });
    }

    private function registerTemplateErrorHandler(): void
    {
        $this->singleton(TemplateErrorHandler::class, function () {
            return new TemplateErrorHandler(true); // Debug mode
        });
    }

    // ===================================================================
    // INTEGRATION SERVICES - SIMPLIFIED
    // ===================================================================

    private function registerIntegrationServices(): void
    {
        $this->registerViewRenderer();
    }

    /**
     * SIMPLIFIED: ViewRenderer mit minimalen Dependencies
     */
    private function registerViewRenderer(): void
    {
        $this->singleton(ViewRenderer::class, function () {
            $translator = null; // Kein Translator für jetzt
            $csrf = $this->container->has(Csrf::class)
                ? $this->container->get(Csrf::class)
                : null;
            $jsAssetManager = null; // Kein AssetManager für jetzt

            return new ViewRenderer(
                engine: $this->container->get(TemplateEngine::class),
                translator: $translator,
                csrf: $csrf,
                jsAssetManager: $jsAssetManager,
                configManager: null
            );
        });
    }

    // ===================================================================
    // VALIDATION METHODS - SAFE
    // ===================================================================

    /**
     * SAFE: Template Directory Validation ohne Exceptions
     */
    private function safeValidateTemplateDirectories(): void
    {
        try {
            $config = $this->loadAndValidateConfig('templating');

            foreach ($config['paths'] ?? ['app/Views'] as $path) {
                $fullPath = $this->basePath($path);
                if (!is_dir($fullPath)) {
                    mkdir($fullPath, 0755, true);
                }
            }
        } catch (\Throwable $e) {
            error_log("Template directory validation failed: " . $e->getMessage());

            // Ensure at least default directory exists
            $defaultPath = $this->basePath('app/Views');
            if (!is_dir($defaultPath)) {
                mkdir($defaultPath, 0755, true);
            }
        }
    }

    /**
     * SAFE: Cache Directory Validation ohne Exceptions
     */
    private function safeValidateCacheDirectory(): void
    {
        try {
            $config = $this->loadAndValidateConfig('templating');
            $cachePath = $this->basePath($config['cache']['path'] ?? 'storage/cache/views');

            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }
        } catch (\Throwable $e) {
            error_log("Cache directory validation failed: " . $e->getMessage());

            // Ensure default cache directory exists
            $defaultCachePath = $this->basePath('storage/cache/views');
            if (!is_dir($defaultCachePath)) {
                mkdir($defaultCachePath, 0755, true);
            }
        }
    }

    protected function bindInterfaces(): void
    {
        // Future interfaces
    }

    // ===================================================================
    // HELPER METHODS
    // ===================================================================

    /**
     * SAFE: Load config ohne Exception bei ConfigManager-Fehlern
     */
    protected function loadAndValidateConfig(string $configName): array
    {
        try {
            $configFile = $this->basePath("app/Config/{$configName}.php");

            if (file_exists($configFile)) {
                return include $configFile;
            }

            // Default fallback config
            return match($configName) {
                'templating' => [
                    'paths' => ['app/Views'],
                    'cache' => [
                        'enabled' => false,
                        'path' => 'storage/cache/views'
                    ],
                    'options' => [
                        'auto_escape' => true,
                        'debug' => true
                    ]
                ],
                default => []
            };
        } catch (\Throwable $e) {
            error_log("Config load failed for {$configName}: " . $e->getMessage());
            return [];
        }
    }
}