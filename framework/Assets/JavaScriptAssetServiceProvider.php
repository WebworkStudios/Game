<?php

declare(strict_types=1);

namespace Framework\Assets;

use Framework\Core\AbstractServiceProvider;
use Framework\Templating\FilterManager;
use Framework\Templating\Filters\JavaScriptFilterRegistrar;
use Framework\Templating\ViewRenderer;
use Framework\Templating\TemplateEngine;
use Framework\Localization\Translator;
use Framework\Security\Csrf;

/**
 * JavaScriptAssetServiceProvider - VEREINFACHT
 *
 * Registriert nur die essentiellen JavaScript-Services:
 * - JavaScriptAssetManager für Actions
 * - Template-Filter für JavaScript-Assets
 * - ViewRenderer-Integration für automatische Script-Injection
 */
class JavaScriptAssetServiceProvider extends AbstractServiceProvider
{
    /**
     * Abhängigkeiten validieren
     */
    protected function validateDependencies(): void
    {
        $this->validateJavaScriptDirectory();
    }

    /**
     * Services registrieren - VEREINFACHT
     */
    protected function registerServices(): void
    {
        // JavaScript Asset Manager als Singleton
        $this->singleton(JavaScriptAssetManager::class, function () {
            $config = $this->getAssetConfig();

            return new JavaScriptAssetManager(
                publicPath: $config['public_path'],
                baseUrl: $config['base_url'],
                debugMode: $config['debug_mode']
            );
        });

        // ViewRenderer mit JavaScript-Integration
        $this->singleton('enhanced_view_renderer', function () {
            $assetManager = $this->container->get(JavaScriptAssetManager::class);

            return new ViewRenderer(
                engine: $this->container->get(TemplateEngine::class),
                translator: $this->container->has(Translator::class) ? $this->container->get(Translator::class) : null,
                csrf: $this->container->has(Csrf::class) ? $this->container->get(Csrf::class) : null
            );
        });
    }

    /**
     * Services konfigurieren - VEREINFACHT
     */
    public function boot(): void
    {
        // Nur JavaScript-Filter registrieren
        $this->registerJavaScriptFilters();
    }

    /**
     * JavaScript-Verzeichnis validieren
     */
    private function validateJavaScriptDirectory(): void
    {
        $config = $this->getAssetConfig();
        $jsPath = $this->basePath($config['public_path']);

        if (!is_dir($jsPath)) {
            if (!mkdir($jsPath, 0755, true)) {
                throw new \RuntimeException("Cannot create JavaScript directory: {$jsPath}");
            }
        }
    }

    /**
     * JavaScript-Filter registrieren
     */
    private function registerJavaScriptFilters(): void
    {
        try {
            $filterManager = $this->container->get(FilterManager::class);
            $assetManager = $this->container->get(JavaScriptAssetManager::class);

            JavaScriptFilterRegistrar::register($filterManager, $assetManager);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to register JavaScript filters: " . $e->getMessage());
        }
    }

    /**
     * Asset-Konfiguration laden
     */
    private function getAssetConfig(): array
    {
        $configPath = $this->basePath('app/Config/assets.php');

        if (file_exists($configPath)) {
            $config = require $configPath;
            return $config['javascript'] ?? [];
        }

        // Einfache Fallback-Konfiguration
        return [
            'public_path' => 'public/js/',
            'base_url' => '/js/',
            'debug_mode' => true
        ];
    }
}