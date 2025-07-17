<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Core\AbstractServiceProvider;
use Framework\Core\ConfigValidation;
use Framework\Localization\Translator;
use Framework\Security\Csrf;
use Framework\Templating\Filters\FilterRegistry;
use Framework\Templating\Filters\FilterExecutor;

/**
 * Templating Service Provider - Registriert Template Services im Framework
 *
 * UPDATED: Angepasst für neue FilterManager-Architektur mit SRP-konformer Struktur
 */
class TemplatingServiceProvider extends AbstractServiceProvider
{
    use ConfigValidation;

    /**
     * Validiert Template-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // Config-Validierung
        $this->ensureConfigExists('templating');

        // Template-spezifische Validierungen
        $this->validateTemplateDirectories();
        $this->validateCacheDirectory();
    }

    /**
     * Registriert alle Templating Services
     */
    protected function registerServices(): void
    {
        $this->registerTemplateCache();
        $this->registerFilterServices();
        $this->registerTemplateEngine();
        $this->registerViewRenderer();
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
            $autoReload = $config['cache']['auto_reload'] ?? true;

            return new TemplateCache($cachePath, $enabled, $autoReload);
        });
    }

    /**
     * Registriert Filter-Services (neue Architektur)
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
     * Registriert Template Engine als Singleton
     */
    private function registerTemplateEngine(): void
    {
        $this->singleton(TemplateEngine::class, function () {
            $config = $this->loadAndValidateConfig('templating');

            $cache = null;
            if ($config['cache']['enabled'] ?? false) {
                $cache = $this->get(TemplateCache::class);
            }

            // Konvertiere relative Pfade zu absoluten Pfaden
            $templatePaths = [];
            foreach ($config['paths'] ?? ['app/Views'] as $path) {
                $templatePaths[] = $this->basePath(ltrim($path, '/'));
            }

            // TemplateEngine mit FilterManager-Dependency
            return new TemplateEngine(
                templatePaths: $templatePaths,
                cache: $cache,
                autoEscape: $config['options']['auto_escape'] ?? true,
                filterManager: $this->get(FilterManager::class)
            );
        });
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
     * Bindet Templating-Interfaces (erweitert für neue Architektur)
     */
    protected function bindInterfaces(): void
    {
        // Hier könnten Template-Interfaces gebunden werden
        // $this->bind(TemplateEngineInterface::class, TemplateEngine::class);
        // $this->bind(FilterManagerInterface::class, FilterManager::class);
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
            if (!is_dir($fullPath) && !mkdir($fullPath, 0755, true)) {
                throw new \RuntimeException("Cannot create template directory: {$fullPath}");
            }
        }
    }

    /**
     * Validiert Cache-Verzeichnis
     */
    private function validateCacheDirectory(): void
    {
        $config = $this->loadAndValidateConfig('templating');

        if ($config['cache']['enabled'] ?? true) {
            $cachePath = $this->basePath($config['cache']['path'] ?? 'storage/cache/views');
            if (!is_dir($cachePath) && !mkdir($cachePath, 0755, true)) {
                throw new \RuntimeException("Cannot create template cache directory: {$cachePath}");
            }
        }
    }
}