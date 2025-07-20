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
 * TemplatingServiceProvider - FIXED für neue Cache-Abstraktion
 */
class TemplatingServiceProvider extends AbstractServiceProvider
{
    use ConfigValidation;

    protected function validateDependencies(): void
    {
        $this->ensureConfigExists('templating');
        $this->validateTemplateDirectories();
        $this->validateCacheDirectory();
    }

    protected function registerServices(): void
    {
        $this->registerSupportServices();
        $this->registerParsingServices();
        $this->registerRenderingServices();
        $this->registerCoordinationServices();
        $this->registerSpecializedServices();
        $this->registerIntegrationServices();
    }

    // ===================================================================
    // FIXED: Template Cache Registration
    // ===================================================================

    /**
     * FIXED: Registriert Template Cache mit neuer API
     */
    private function registerTemplateCache(): void
    {
        $this->singleton(TemplateCache::class, function () {
            $config = $this->loadAndValidateConfig('templating');
            $cacheDir = $this->basePath($config['cache']['path'] ?? 'storage/cache/views');
            $enabled = $config['cache']['enabled'] ?? true;

            // FIXED: Nutzt neue factory method statt named parameters
            return TemplateCache::create($cacheDir, $enabled);
        });
    }

    // ===================================================================
    // ALTERNATIVE: Direct CacheManager Usage (Optional)
    // ===================================================================

    /**
     * ALTERNATIVE: Template Cache mit direkter CacheManager-Nutzung
     */
    private function registerTemplateCacheAlternative(): void
    {
        $this->singleton(TemplateCache::class, function () {
            $config = $this->loadAndValidateConfig('templating');
            $enabled = $config['cache']['enabled'] ?? true;

            // Nutzt registrierten CacheManager
            $cacheManager = $this->container->get(CacheManager::class);

            return new TemplateCache($cacheManager, $enabled);
        });
    }

    // ===================================================================
    // REST OF THE SERVICE REGISTRATIONS (unchanged)
    // ===================================================================

    private function registerSupportServices(): void
    {
        $this->registerTemplateCache();  // ← FIXED method
        $this->registerFilterServices();
    }

    private function registerSpecializedServices(): void
    {
        $this->registerTemplateConfigManager();
        $this->registerTemplateDataInjector();
        $this->registerAssetIntegrationManager();
        $this->registerTemplateErrorHandler();
    }

    private function registerTemplateConfigManager(): void
    {
        $this->singleton(TemplateConfigManager::class, function () {
            $configManager = $this->container->has(ConfigManager::class)
                ? $this->container->get(ConfigManager::class)
                : null;

            return new TemplateConfigManager($configManager);
        });
    }

    private function registerTemplateDataInjector(): void
    {
        $this->singleton(TemplateDataInjector::class, function () {
            $csrf = $this->container->has(Csrf::class)
                ? $this->container->get(Csrf::class)
                : null;

            $configManager = $this->container->get(TemplateConfigManager::class);

            return new TemplateDataInjector($csrf, $configManager);
        });
    }

    private function registerAssetIntegrationManager(): void
    {
        $this->singleton(AssetIntegrationManager::class, function () {
            $jsAssetManager = $this->container->has(JavaScriptAssetManager::class)
                ? $this->container->get(JavaScriptAssetManager::class)
                : new JavaScriptAssetManager();

            return new AssetIntegrationManager($jsAssetManager);
        });
    }

    private function registerTemplateErrorHandler(): void
    {
        $this->singleton(TemplateErrorHandler::class, function () {
            $configManager = $this->container->get(TemplateConfigManager::class);

            return new TemplateErrorHandler(
                debugMode: $configManager->isDebugMode()
            );
        });
    }

    private function registerIntegrationServices(): void
    {
        $this->registerRefactoredViewRenderer();
    }

    private function registerRefactoredViewRenderer(): void
    {
        $this->singleton(ViewRenderer::class, function () {
            $translator = $this->container->has(Translator::class)
                ? $this->container->get(Translator::class)
                : null;

            $csrf = $this->container->has(Csrf::class)
                ? $this->container->get(Csrf::class)
                : null;

            $jsAssetManager = $this->container->has(JavaScriptAssetManager::class)
                ? $this->container->get(JavaScriptAssetManager::class)
                : null;

            $configManager = $this->container->has(ConfigManager::class)
                ? $this->container->get(ConfigManager::class)
                : null;

            return new ViewRenderer(
                engine: $this->container->get(TemplateEngine::class),
                translator: $translator,
                csrf: $csrf,
                jsAssetManager: $jsAssetManager,
                configManager: $configManager
            );
        });
    }

    private function registerFilterServices(): void
    {
        $this->singleton(FilterRegistry::class);
        $this->singleton(FilterExecutor::class, function () {
            return new FilterExecutor($this->container->get(FilterRegistry::class));
        });
        $this->singleton(FilterManager::class, function () {
            $translator = $this->container->has(Translator::class)
                ? $this->container->get(Translator::class)
                : null;

            return new FilterManager($translator);
        });
    }

    private function registerParsingServices(): void
    {
        $this->registerTemplatePathResolver();
        $this->registerTemplateTokenizer();
        $this->registerControlFlowParser();
        $this->registerTemplateParser();
    }

    private function registerRenderingServices(): void
    {
        $this->registerTemplateVariableResolver();
        $this->registerTemplateRenderer();
    }

    private function registerCoordinationServices(): void
    {
        $this->registerTemplateEngine();
    }

    private function registerTemplatePathResolver(): void
    {
        $this->singleton(TemplatePathResolver::class, function () {
            $config = $this->loadAndValidateConfig('templating');
            $templatePaths = [];

            foreach ($config['paths'] ?? ['app/Views'] as $path) {
                $templatePaths[] = $this->basePath(ltrim($path, '/'));
            }

            return new TemplatePathResolver($templatePaths);
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
                $this->container->get(FilterManager::class),
                $this->container->get(TemplatePathResolver::class)
            );
        });
    }

    private function registerTemplateEngine(): void
    {
        $this->singleton(TemplateEngine::class, function () {
            return new TemplateEngine(
                $this->container->get(TemplatePathResolver::class),
                $this->container->get(TemplateCache::class),
                $this->container->get(FilterManager::class)
            );
        });
    }

    private function validateTemplateDirectories(): void
    {
        $config = $this->loadAndValidateConfig('templating');

        foreach ($config['paths'] ?? ['app/Views'] as $path) {
            $fullPath = $this->basePath($path);
            if (!is_dir($fullPath)) {
                if (!mkdir($fullPath, 0755, true)) {
                    throw new \RuntimeException("Cannot create template directory: {$fullPath}");
                }
            }
        }
    }

    private function validateCacheDirectory(): void
    {
        $config = $this->loadAndValidateConfig('templating');
        $cachePath = $this->basePath($config['cache']['path'] ?? 'storage/cache/views');

        if (!is_dir($cachePath)) {
            if (!mkdir($cachePath, 0755, true)) {
                throw new \RuntimeException("Cannot create template cache directory: {$cachePath}");
            }
        }
    }

    protected function bindInterfaces(): void
    {
        // Future template interfaces can be bound here
    }
}