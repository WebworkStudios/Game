<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Core\AbstractServiceProvider;
use Framework\Core\ConfigValidation;
use Framework\Localization\Translator;
use Framework\Security\Csrf;

/**
 * Templating Service Provider - Registriert Template Services im Framework
 *
 * BEREINIGT: Verwendet ConfigValidation Trait, eliminiert Code-Duplikation
 */
class TemplatingServiceProvider extends AbstractServiceProvider
{
    use ConfigValidation;

    /**
     * Validiert Template-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // Config-Validierung (eliminiert die vorherige Duplikation)
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
        $this->registerFilterManager();
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
     * Registriert Filter Manager als Singleton
     */
    private function registerFilterManager(): void
    {
        $this->singleton(FilterManager::class, function () {
            // Try to get Translator, but don't fail if not available
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

            return new TemplateEngine(
                templatePaths: $templatePaths,
                cache: $cache,
                autoEscape: $config['options']['auto_escape'] ?? true
            );
        });
    }

    /**
     * Registriert ViewRenderer als Singleton mit proper DI
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
     * Bindet Templating-Interfaces
     */
    protected function bindInterfaces(): void
    {
        // Hier könnten Template-Interfaces gebunden werden
        // $this->bind(TemplateEngineInterface::class, TemplateEngine::class);
    }

    /**
     * Validiert Template-Verzeichnisse (Templating-spezifisch)
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
     * Validiert Cache-Verzeichnis (Templating-spezifisch)
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