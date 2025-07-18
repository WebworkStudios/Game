<?php

declare(strict_types=1);

namespace Framework\Assets;

use Framework\Core\AbstractServiceProvider;
use Framework\Core\ServiceContainer;
use Framework\Templating\FilterManager;
use Framework\Templating\Filters\JavaScriptFilterRegistrar;
use Framework\Templating\ViewRenderer;
use Framework\Templating\TemplateEngine;
use Framework\Localization\Translator;
use Framework\Security\Csrf;

/**
 * JavaScriptAssetServiceProvider - Service Provider für JavaScript Asset Management
 *
 * Registriert alle JavaScript-bezogenen Services im Container und
 * integriert sie in die bestehende Template-Engine.
 */
class JavaScriptAssetServiceProvider extends AbstractServiceProvider
{
    /**
     * Abhängigkeiten validieren
     */
    protected function validateDependencies(): void
    {
        // JavaScript-spezifische Validierungen
        $this->validateJavaScriptDirectory();
        $this->validateAssetConfiguration();
    }

    /**
     * Services im Container registrieren
     */
    public function register(ServiceContainer $container): void
    {
        // JavaScript Asset Manager registrieren
        $container->singleton(JavaScriptAssetManager::class, function () {
            $config = $this->getAssetConfig();

            return new JavaScriptAssetManager(
                publicPath: $config['public_path'],
                baseUrl: $config['base_url'],
                debugMode: $config['debug_mode']
            );
        });

        // ViewRenderer mit JavaScript-Integration erweitern
        $container->extend(ViewRenderer::class, function (ViewRenderer $viewRenderer, ServiceContainer $container) {
            $assetManager = $container->get(JavaScriptAssetManager::class);

            // Neue ViewRenderer-Instanz mit Asset Manager erstellen
            return new ViewRenderer(
                engine: $container->get(TemplateEngine::class),
                translator: $container->has(Translator::class) ? $container->get(Translator::class) : null,
                csrf: $container->has(Csrf::class) ? $container->get(Csrf::class) : null,
                assetManager: $assetManager
            );
        });

        // JavaScript Helper Functions global verfügbar machen
        $this->registerGlobalHelpers($container);
    }

    /**
     * Services nach der Registrierung konfigurieren
     */
    public function boot(ServiceContainer $container): void
    {
        // JavaScript-Filter in FilterManager registrieren
        $this->registerJavaScriptFilters($container);

        // Route-basierte Scripts konfigurieren
        $this->configureRouteBasedScripts($container);

        // Environment-spezifische Scripts hinzufügen
        $this->addEnvironmentScripts($container);
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
     * Asset-Konfiguration validieren
     */
    private function validateAssetConfiguration(): void
    {
        $config = $this->getAssetConfig();

        // Erforderliche Konfigurationswerte prüfen
        $required = ['public_path', 'base_url'];
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new \RuntimeException("Missing required asset configuration: {$key}");
            }
        }
    }

    /**
     * JavaScript-Filter im FilterManager registrieren
     */
    private function registerJavaScriptFilters(ServiceContainer $container): void
    {
        try {
            $filterManager = $container->get(FilterManager::class);
            $assetManager = $container->get(JavaScriptAssetManager::class);

            JavaScriptFilterRegistrar::register($filterManager, $assetManager);
        } catch (\Throwable $e) {
            // FilterManager nicht verfügbar - Skip
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("JavaScript filters could not be registered: " . $e->getMessage());
            }
        }
    }

    /**
     * Route-basierte Script-Registrierung konfigurieren
     */
    private function configureRouteBasedScripts(ServiceContainer $container): void
    {
        $config = $this->getAssetConfig();
        $routeScripts = $config['route_scripts'] ?? [];

        if (empty($routeScripts)) {
            return;
        }

        // Route-Script-Loader registrieren
        $container->singleton('route_script_loader', function () use ($routeScripts, $container) {
            return new RouteScriptLoader($routeScripts, $container);
        });
    }

    /**
     * Environment-spezifische Scripts hinzufügen
     */
    private function addEnvironmentScripts(ServiceContainer $container): void
    {
        $config = $this->getAssetConfig();
        $envScripts = $config['environment_scripts'] ?? [];
        $currentEnv = $_ENV['APP_ENV'] ?? 'production';

        if (isset($envScripts[$currentEnv])) {
            $assetManager = $container->get(JavaScriptAssetManager::class);

            foreach ($envScripts[$currentEnv] as $script) {
                $assetManager->addInlineScript($script, 10); // Hohe Priorität
            }
        }
    }

    /**
     * Global Helper Functions registrieren
     */
    private function registerGlobalHelpers(ServiceContainer $container): void
    {
        // Asset Manager global verfügbar machen
        $GLOBALS['jsAssetManager'] = $container->get(JavaScriptAssetManager::class);

        // Helper-Funktionen laden
        $this->loadGlobalHelpers();
    }

    /**
     * Helper-Funktionen laden
     */
    private function loadGlobalHelpers(): void
    {
        // Nur laden wenn nicht bereits definiert
        if (!function_exists('js_asset')) {
            $this->defineGlobalHelpers();
        }
    }

    /**
     * Global Helper Functions definieren
     */
    private function defineGlobalHelpers(): void
    {
        // JavaScript-Asset hinzufügen
        if (!function_exists('js_asset')) {
            function js_asset(string $filename, array $attributes = ['defer' => true]): void {
                $assetManager = $GLOBALS['jsAssetManager'] ?? null;
                if ($assetManager instanceof JavaScriptAssetManager) {
                    $assetManager->addScript($filename, $attributes);
                }
            }
        }

        // JavaScript-Module hinzufügen
        if (!function_exists('js_module')) {
            function js_module(string $filename, int $priority = 100): void {
                $assetManager = $GLOBALS['jsAssetManager'] ?? null;
                if ($assetManager instanceof JavaScriptAssetManager) {
                    $assetManager->addModule($filename, $priority);
                }
            }
        }

        // Inline-JavaScript hinzufügen
        if (!function_exists('js_inline')) {
            function js_inline(string $content, int $priority = 50): void {
                $assetManager = $GLOBALS['jsAssetManager'] ?? null;
                if ($assetManager instanceof JavaScriptAssetManager) {
                    $assetManager->addInlineScript($content, $priority);
                }
            }
        }

        // JavaScript-Bundle laden
        if (!function_exists('js_bundle')) {
            function js_bundle(string $bundleName): void {
                $configPath = __DIR__ . '/../../app/Config/assets.php';

                if (file_exists($configPath)) {
                    $config = require $configPath;
                    $bundles = $config['javascript']['bundles'] ?? [];

                    if (isset($bundles[$bundleName])) {
                        foreach ($bundles[$bundleName] as $script) {
                            js_asset($script);
                        }
                    }
                }
            }
        }

        // Alle JavaScript-Assets rendern
        if (!function_exists('render_js_assets')) {
            function render_js_assets(): string {
                $assetManager = $GLOBALS['jsAssetManager'] ?? null;

                if ($assetManager instanceof JavaScriptAssetManager) {
                    return $assetManager->render();
                }

                return '';
            }
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

        // Fallback-Konfiguration
        return [
            'public_path' => 'public/js/',
            'base_url' => '/js/',
            'debug_mode' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            'route_scripts' => [],
            'environment_scripts' => [],
        ];
    }
}

// =============================================================================
// Route Script Loader - Hilfsklasse für Route-basierte Scripts
// =============================================================================

/**
 * RouteScriptLoader - Lädt Scripts basierend auf aktueller Route
 */
class RouteScriptLoader
{
    public function __construct(
        private array $routeScripts,
        private ServiceContainer $container
    ) {}

    /**
     * Scripts für Route laden
     */
    public function loadScriptsForRoute(string $routeName): void
    {
        $assetManager = $this->container->get(JavaScriptAssetManager::class);

        foreach ($this->routeScripts as $pattern => $scripts) {
            if ($this->matchesRoute($routeName, $pattern)) {
                foreach ($scripts as $script) {
                    $assetManager->addScript($script);
                }
            }
        }
    }

    /**
     * Prüft ob Route dem Pattern entspricht
     */
    private function matchesRoute(string $routeName, string $pattern): bool
    {
        // Exakte Übereinstimmung
        if ($pattern === $routeName) {
            return true;
        }

        // Wildcard-Support (z.B. admin.*)
        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);
            return str_starts_with($routeName, $prefix);
        }

        return false;
    }
}


// =============================================================================
// Integration in ApplicationKernel
// =============================================================================

/**
 * Beispiel für Integration in den ApplicationKernel
 *
 * In framework/Core/ServiceProviderRegistry.php würde folgendes hinzugefügt:
 */
/*
class ServiceProviderRegistry
{
    private array $providers = [
        SecurityServiceProvider::class,
        DatabaseServiceProvider::class,
        ValidationServiceProvider::class,
        LocalizationServiceProvider::class,
        TemplatingServiceProvider::class,
        \Framework\Assets\JavaScriptAssetServiceProvider::class, // HINZUGEFÜGT
    ];
}
*/

// =============================================================================
// Action-Integration
// =============================================================================

/**
 * Beispiel für die Verwendung in Actions
 */
/*
use Framework\Assets\JavaScriptAssetManager;

#[Route(path: '/match/{id}', methods: ['GET'], name: 'match.show')]
class ShowMatchAction
{
    public function __construct(
        private readonly MatchService $matchService,
        private readonly ResponseFactory $responseFactory,
        private readonly JavaScriptAssetManager $assetManager
    ) {}

    public function __invoke(Request $request): Response
    {
        $matchId = $request->getPathParameter('id');
        $match = $this->matchService->findById($matchId);

        // Match-spezifische Scripts hinzufügen
        $this->assetManager->addModule('match-live.js');
        $this->assetManager->addScript('charts/performance.js');

        // Match-Daten für JavaScript bereitstellen
        $this->assetManager->addInlineScript(
            'window.matchData = ' . json_encode([
                'id' => $match->getId(),
                'isLive' => $match->isLive(),
                'status' => $match->getStatus()
            ]) . ';'
        );

        return $this->responseFactory->view('match/show', [
            'match' => $match
        ]);
    }
}
*/