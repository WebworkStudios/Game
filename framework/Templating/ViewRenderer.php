<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Assets\JavaScriptAssetManager;
use Framework\Core\ConfigManager;
use Framework\Http\HttpStatus;
use Framework\Http\Response;
use Framework\Localization\Translator;
use Framework\Security\Csrf;
use Framework\Templating\Services\AssetIntegrationManager;
use Framework\Templating\Services\TemplateConfigManager;
use Framework\Templating\Services\TemplateDataInjector;
use Framework\Templating\Services\TemplateErrorHandler;

/**
 * ViewRenderer - REFACTORED für SRP-Konformität
 *
 * NEUE ARCHITEKTUR:
 * ✅ SRP: ViewRenderer koordiniert nur, delegiert an spezialisierte Services
 * ✅ Dependency Injection: Klare Service-Abhängigkeiten
 * ✅ Single Responsibility: Jede Aufgabe hat eine eigene Klasse
 * ✅ Testbarkeit: Alle Services einzeln testbar
 *
 * DELEGIERTE VERANTWORTLICHKEITEN:
 * - Datenaufbereitung → TemplateDataInjector
 * - Asset-Management → AssetIntegrationManager
 * - Error-Handling → TemplateErrorHandler
 * - Konfiguration → TemplateConfigManager
 */
readonly class ViewRenderer
{
    private TemplateDataInjector $dataInjector;
    private AssetIntegrationManager $assetManager;
    private TemplateErrorHandler $errorHandler;
    private TemplateConfigManager $configManager;

    public function __construct(
        private TemplateEngine $engine,
        ?Translator $translator = null,
        ?Csrf $csrf = null,
        ?JavaScriptAssetManager $jsAssetManager = null,
        ?ConfigManager $configManager = null
    ) {
        // Spezialisierte Services erstellen (SRP-konform)
        $this->configManager = new TemplateConfigManager($configManager);

        $this->dataInjector = new TemplateDataInjector(
            translator: $translator,
            csrf: $csrf,
            appConfig: $this->configManager->getConfig()
        );

        $this->assetManager = new AssetIntegrationManager(
            assetManager: $jsAssetManager ?? new JavaScriptAssetManager(
            publicPath: 'public/js/',
            baseUrl: '/js/',
            debugMode: $this->configManager->isDebugMode()
        )
        );

        $this->errorHandler = new TemplateErrorHandler(
            debugMode: $this->configManager->isDebugMode()
        );
    }

    /**
     * Factory-Methode für einfache Instanziierung
     */
    public static function create(
        TemplateEngine $engine,
        ?Translator $translator = null,
        ?Csrf $csrf = null,
        ?JavaScriptAssetManager $assetManager = null,
        ?ConfigManager $configManager = null
    ): self {
        return new self(
            engine: $engine,
            translator: $translator,
            csrf: $csrf,
            jsAssetManager: $assetManager,
            configManager: $configManager
        );
    }

    /**
     * HAUPTMETHODE: Template rendern mit Services-Delegation
     */
    public function render(
        string $template,
        array $data = [],
        HttpStatus $status = HttpStatus::OK,
        array $headers = []
    ): Response {
        try {
            // 1. Daten aufbereiten (delegiert an TemplateDataInjector)
            $enrichedData = $this->dataInjector->injectFrameworkServices($data);
            $enrichedData = $this->assetManager->injectAssetHelpers($enrichedData);

            // 2. Template rendern (einzige verbleibende Kernverantwortung)
            $html = $this->engine->render($template, $enrichedData);

            // 3. Assets einbinden (delegiert an AssetIntegrationManager)
            $html = $this->assetManager->injectJavaScriptAssets($html);

            // 4. Response erstellen
            return new Response(
                status: $status,
                headers: ['Content-Type' => 'text/html; charset=UTF-8', ...$headers],
                body: $html
            );

        } catch (\Throwable $e) {
            // Error-Handling delegiert an TemplateErrorHandler
            return $this->errorHandler->handleRenderError($e, $template, $data, $status, $headers);
        }
    }

    /**
     * Asset Manager Zugriff für externe Services
     */
    public function getAssetManager(): AssetIntegrationManager
    {
        return $this->assetManager;
    }

    /**
     * Config Manager Zugriff
     */
    public function getConfigManager(): TemplateConfigManager
    {
        return $this->configManager;
    }

    /**
     * Template Engine Zugriff
     */
    public function getTemplateEngine(): TemplateEngine
    {
        return $this->engine;
    }
}