<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Core\AbstractServiceProvider;
use Framework\Core\ConfigValidation;
use Framework\Localization\Translator;
use Framework\Security\Csrf;
use Framework\Templating\Filters\FilterRegistry;
use Framework\Templating\Filters\FilterExecutor;
use Framework\Templating\Parsing\TemplateParser;
use Framework\Templating\Parsing\TemplateTokenizer;
use Framework\Templating\Parsing\ControlFlowParser;
use Framework\Templating\Parsing\TemplatePathResolver;
use Framework\Templating\Rendering\TemplateRenderer;
use Framework\Templating\Rendering\TemplateVariableResolver;

/**
 * TemplatingServiceProvider - Registriert die neue SRP-konforme Template-Architektur
 *
 * REFACTORED: Vollständig umgestaltet für modulare, SRP-konforme Architektur
 *
 * Neue Architektur:
 * - Parsing Layer: Tokenizer, Parser, PathResolver
 * - Rendering Layer: Renderer, VariableResolver
 * - Coordination Layer: TemplateEngine
 * - Support Layer: Cache, Filters
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
     * Registriert alle Template-Services in der neuen Architektur
     */
    protected function registerServices(): void
    {
        // Support Layer - Foundation Services
        $this->registerSupportServices();

        // Parsing Layer - Template-Parsing Pipeline
        $this->registerParsingServices();

        // Rendering Layer - Template-Rendering Pipeline
        $this->registerRenderingServices();

        // Coordination Layer - Main TemplateEngine
        $this->registerCoordinationServices();

        // Integration Layer - ViewRenderer für Response Factory
        $this->registerIntegrationServices();
    }

    /**
     * Registriert Support-Services (Cache, Filter)
     */
    private function registerSupportServices(): void
    {
        $this->registerTemplateCache();
        $this->registerFilterServices();
    }

    /**
     * Registriert Template Cache als Singleton
     */
    private function registerTemplateCache(): void
    {
        $this->singleton(TemplateCache::class, function () {
            $config = $this->loadAndValidateConfig('templating');

            $cachePath = $this->basePath($config['cache']['path'] ?? 'storage/cache/views');
            $enabled = $config['cache']['enabled'] ?? true;

            return new TemplateCache($cachePath, $enabled);
        });
    }

    /**
     * Registriert Filter-Services (bestehende Architektur)
     */
    private function registerFilterServices(): void
    {
        // FilterRegistry als Singleton
        $this->singleton(FilterRegistry::class, function () {
            return new FilterRegistry();
        });

        // FilterExecutor als Singleton
        $this->singleton(FilterExecutor::class, function () {
            return new FilterExecutor($this->get(FilterRegistry::class));
        });

        // FilterManager als Singleton (Facade)
        $this->singleton(FilterManager::class, function () {
            // Try to get Translator gracefully
            $translator = null;
            try {
                $translator = $this->get(Translator::class);
            } catch (\Throwable) {
                // Translator not available - FilterManager will work without it
            }

            return new FilterManager($translator);
        });
    }

    /**
     * Registriert Parsing-Services (neue SRP-konforme Architektur)
     */
    private function registerParsingServices(): void
    {
        $this->registerTemplatePathResolver();
        $this->registerTemplateTokenizer();
        $this->registerControlFlowParser();
        $this->registerTemplateParser();
    }

    /**
     * Registriert TemplatePathResolver als Singleton
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
     * Registriert TemplateTokenizer als Singleton
     */
    private function registerTemplateTokenizer(): void
    {
        $this->singleton(TemplateTokenizer::class, function () {
            return new TemplateTokenizer();
        });
    }

    /**
     * Registriert ControlFlowParser als Singleton
     */
    private function registerControlFlowParser(): void
    {
        $this->singleton(ControlFlowParser::class, function () {
            return new ControlFlowParser();
        });
    }

    /**
     * Registriert TemplateParser als Singleton
     */
    private function registerTemplateParser(): void
    {
        $this->singleton(TemplateParser::class, function () {
            return new TemplateParser(
                $this->get(TemplateTokenizer::class),
                $this->get(ControlFlowParser::class),
                $this->get(TemplatePathResolver::class)
            );
        });
    }

    /**
     * Registriert Rendering-Services (neue SRP-konforme Architektur)
     */
    private function registerRenderingServices(): void
    {
        $this->registerTemplateVariableResolver();
        $this->registerTemplateRenderer();
    }

    /**
     * Registriert TemplateVariableResolver als Singleton
     */
    private function registerTemplateVariableResolver(): void
    {
        $this->singleton(TemplateVariableResolver::class, function () {
            return new TemplateVariableResolver();
        });
    }

    /**
     * Registriert TemplateRenderer als Singleton
     */
    private function registerTemplateRenderer(): void
    {
        $this->singleton(TemplateRenderer::class, function () {
            return new TemplateRenderer(
                $this->get(TemplateVariableResolver::class),
                $this->get(FilterManager::class),
                $this->get(TemplatePathResolver::class)
            );
        });
    }

    /**
     * Registriert Coordination-Services (TemplateEngine)
     */
    private function registerCoordinationServices(): void
    {
        $this->registerTemplateEngine();
    }

    /**
     * Registriert die neue TemplateEngine als Singleton
     */
    private function registerTemplateEngine(): void
    {
        $this->singleton(TemplateEngine::class, function () {
            return new TemplateEngine(
                $this->get(TemplatePathResolver::class),
                $this->get(TemplateCache::class),
                $this->get(FilterManager::class)
            );
        });
    }

    /**
     * Registriert Integration-Services (ViewRenderer)
     */
    private function registerIntegrationServices(): void
    {
        $this->registerViewRenderer();
    }

    /**
     * Registriert ViewRenderer als Singleton
     */
    private function registerViewRenderer(): void
    {
        $this->singleton(ViewRenderer::class, function () {
            // Try to get optional dependencies gracefully
            $translator = null;
            $csrf = null;

            try {
                $translator = $this->get(Translator::class);
            } catch (\Throwable) {
                // Translator not available - ViewRenderer can work without it
            }

            try {
                $csrf = $this->get(Csrf::class);
            } catch (\Throwable) {
                // CSRF not available - ViewRenderer can work without it
            }

            return new ViewRenderer(
                engine: $this->get(TemplateEngine::class),
                translator: $translator,
                csrf: $csrf
            );
        });
    }

    /**
     * Bindet Template-Interfaces (bereit für zukünftige Abstractions)
     */
    protected function bindInterfaces(): void
    {
    }

    /**
     * Registriert Custom Filter aus Config
     */
    private function registerCustomFilters(): void
    {
        $config = $this->loadAndValidateConfig('templating');

        if (!isset($config['filters']['custom_filter_classes'])) {
            return;
        }

        $filterManager = $this->get(FilterManager::class);

        foreach ($config['filters']['custom_filter_classes'] as $filterName => $filterClass) {
            if (!class_exists($filterClass)) {
                continue;
            }

            // Registriere Custom Filter Klasse
            $filterManager->register($filterName, function (mixed $value, ...$parameters) use ($filterClass) {
                return $filterClass::handle($value, ...$parameters);
            });
        }
    }

    /**
     * Überschreibt register() um Custom Filter zu registrieren
     */
    final public function register(): void
    {
        // Standard-Registrierung
        parent::register();

        // Custom Filter registrieren (nach Standard-Services)
        $this->registerCustomFilters();
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
                throw new \RuntimeException("Cannot create cache directory: {$cachePath}");
            }
        }
    }
}