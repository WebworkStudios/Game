<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Core\AbstractServiceProvider;
use Framework\Localization\Translator;
use Framework\Security\Csrf;

/**
 * Templating Service Provider - Registriert Template Services im Framework
 *
 * BEREINIGT: Keine Default-Provider mehr - Config-Dateien sind die einzige Quelle
 */
class TemplatingServiceProvider extends AbstractServiceProvider
{
    private const string CONFIG_PATH = 'app/Config/templating.php';

    /**
     * Validiert Template-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // Prüfe ob Config-Datei existiert
        if (!$this->configExists()) {
            throw new \RuntimeException(
                "Templating config file not found: " . self::CONFIG_PATH . "\n" .
                "Please create this file or run: php artisan config:publish templating"
            );
        }

        // Prüfe ob Template-Verzeichnisse existieren/erstellt werden können
        $config = $this->getConfig(self::CONFIG_PATH);

        foreach ($config['paths'] ?? ['app/Views'] as $path) {
            $fullPath = $this->basePath($path);
            if (!is_dir($fullPath) && !mkdir($fullPath, 0755, true)) {
                throw new \RuntimeException("Cannot create template directory: {$fullPath}");
            }
        }

        // Prüfe ob Cache-Verzeichnis erstellt werden kann
        if ($config['cache']['enabled'] ?? true) {
            $cachePath = $this->basePath($config['cache']['path'] ?? 'storage/cache/views');
            if (!is_dir($cachePath) && !mkdir($cachePath, 0755, true)) {
                throw new \RuntimeException("Cannot create template cache directory: {$cachePath}");
            }
        }
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
            $config = $this->getConfig(self::CONFIG_PATH);

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
            $config = $this->getConfig(self::CONFIG_PATH);

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
            return new ViewRenderer(
                engine: $this->get(TemplateEngine::class),
                translator: $this->get(Translator::class),
                csrf: $this->get(Csrf::class)
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
     * Prüft ob Config-Datei existiert
     */
    private function configExists(): bool
    {
        return file_exists($this->basePath(self::CONFIG_PATH));
    }
}