<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Assets\JavaScriptAssetManager;
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
 * TemplatingServiceProvider - REFACTORED für neue SRP-konforme ViewRenderer-Architektur
 *
 * NEUE SERVICE-REGISTRIERUNG:
 * ✅ Alle spezialisierten Services registrieren
 * ✅ ViewRenderer als Koordinator mit Dependencies
 * ✅ Bestehende Template-Engine-Services bleiben unverändert
 * ✅ Backward-Compatibility gewährleistet
 */
class TemplatingServiceProvider extends AbstractServiceProvider
{
    use ConfigValidation;

    /**
     * Validiert Template-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        $this->ensureConfigExists('templating');
        $this->validateTemplateDirectories();
        $this->validateCacheDirectory();
    }

    /**
     * Registriert alle Template-Services
     */
    protected function registerServices(): void
    {
        $this->registerSupportServices();     // Cache, Filters
        $this->registerParsingServices();     // Tokenizer, Parser, PathResolver
        $this->registerRenderingServices();   // Renderer, VariableResolver
        $this->registerCoordinationServices(); // TemplateEngine

        // NEUE SRP-KONFORME SERVICES
        $this->registerSpecializedServices(); // Data Injector, Asset Manager, etc.
        $this->registerIntegrationServices(); // Refactored ViewRenderer
    }

    /**
     * NEUE METHODE: Registriert spezialisierte Services
     */
    private function registerSpecializedServices(): void
    {
        $this->registerTemplateConfigManager();
        $this->registerTemplateDataInjector();
        $this->registerAssetIntegrationManager();
        $this->registerTemplateErrorHandler();
    }

    /**
     * Registriert TemplateConfigManager
     */
    private function registerTemplateConfigManager(): void
    {
        $this->singleton(TemplateConfigManager::class, function () {
            $configManager = $this->container->has(ConfigManager::class)
                ? $this->container->get(ConfigManager::class)
                : null;

            return new TemplateConfigManager($configManager);
        });
    }

    /**
     * Registriert TemplateDataInjector
     */
    private function registerTemplateDataInjector(): void
    {
        $this->singleton(TemplateDataInjector::class, function () {
            $translator = $this->container->has(Translator::class)
                ? $this->container->get(Translator::class)
                : null;

            $csrf = $this->container->has(Csrf::class)
                ? $this->container->get(Csrf::class)
                : null;

            $configManager = $this->container->get(TemplateConfigManager::class);

            return new TemplateDataInjector(
                translator: $translator,
                csrf: $csrf,
                appConfig: $configManager->getConfig()
            );
        });
    }

    /**
     * Registriert AssetIntegrationManager
     */
    private function registerAssetIntegrationManager(): void
    {
        $this->singleton(AssetIntegrationManager::class, function () {
            $jsAssetManager = $this->container->has(JavaScriptAssetManager::class)
                ? $this->container->get(JavaScriptAssetManager::class)
                : new JavaScriptAssetManager();

            return new AssetIntegrationManager($jsAssetManager);
        });
    }

    /**
     * Registriert TemplateErrorHandler
     */
    private function registerTemplateErrorHandler(): void
    {
        $this->singleton(TemplateErrorHandler::class, function () {
            $configManager = $this->container->get(TemplateConfigManager::class);

            return new TemplateErrorHandler(
                debugMode: $configManager->isDebugMode()
            );
        });
    }

    /**
     * BESTEHENDE SERVICES (unverändert) - Support Services
     */
    private function registerSupportServices(): void
    {
        $this->registerTemplateCache();
        $this->registerFilterServices();
    }

    /**
     * BESTEHENDE SERVICES (unverändert) - Parsing Services
     */
    private function registerParsingServices(): void
    {
        $this->registerTemplatePathResolver();
        $this->registerTemplateTokenizer();
        $this->registerControlFlowParser();
        $this->registerTemplateParser();
    }

    /**
     * BESTEHENDE SERVICES (unverändert) - Rendering Services
     */
    private function registerRenderingServices(): void
    {
        $this->registerTemplateVariableResolver();
        $this->registerTemplateRenderer();
    }

    /**
     * BESTEHENDE SERVICES (unverändert) - Coordination Services
     */
    private function registerCoordinationServices(): void
    {
        $this->registerTemplateEngine();
    }

    /**
     * REFACTORED: Integration Services - Neue ViewRenderer-Architektur
     */
    private function registerIntegrationServices(): void
    {
        $this->registerRefactoredViewRenderer();
    }

    /**
     * Registriert den refactorierten ViewRenderer
     */
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

    // ================================================================
    // BESTEHENDE SERVICE-REGISTRIERUNGEN (unverändert für Kompatibilität)
    // ================================================================

    /**
     * Registriert Template Cache
     */
    private function registerTemplateCache(): void
    {
        $this->singleton(TemplateCache::class, function () {
            $config = $this->loadAndValidateConfig('templating');

            return new TemplateCache(
                cacheDir: $this->basePath($config['cache']['path'] ?? 'storage/cache/views'),
                enabled: $config['cache']['enabled'] ?? true
            );
        });
    }

    /**
     * Registriert Filter-Services
     */
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

    /**
     * Registriert TemplatePathResolver
     */
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

    /**
     * Registriert TemplateTokenizer
     */
    private function registerTemplateTokenizer(): void
    {
        $this->singleton(TemplateTokenizer::class, function () {
            return new TemplateTokenizer();
        });
    }

    /**
     * Registriert ControlFlowParser
     */
    private function registerControlFlowParser(): void
    {
        $this->singleton(ControlFlowParser::class, function () {
            return new ControlFlowParser();
        });
    }

    /**
     * Registriert TemplateParser
     */
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

    /**
     * Registriert TemplateVariableResolver
     */
    private function registerTemplateVariableResolver(): void
    {
        $this->singleton(TemplateVariableResolver::class, function () {
            return new TemplateVariableResolver();
        });
    }

    /**
     * Registriert TemplateRenderer (Low-Level)
     */
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

    /**
     * Registriert TemplateEngine
     */
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

    /**
     * Validiert Template-Verzeichnisse
     */
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

    /**
     * Validiert Cache-Verzeichnis
     */
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

    /**
     * Bindet Template-Interfaces
     */
    protected function bindInterfaces(): void
    {
        // Hier können zukünftige Template-Interfaces gebunden werden
    }
}