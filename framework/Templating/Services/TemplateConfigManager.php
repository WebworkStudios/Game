<?php

declare(strict_types=1);

namespace Framework\Templating\Services;

use Framework\Core\ConfigManager;

/**
 * TemplateConfigManager - SRP: Verantwortlich NUR für Template-Konfiguration
 *
 * Früher: Teil des ViewRenderer (SRP-Verletzung)
 * Jetzt: Spezialisierte Config-Handling-Klasse
 */
readonly class TemplateConfigManager
{
    private array $config;

    public function __construct(?ConfigManager $configManager = null)
    {
        $this->config = $this->loadAppConfig($configManager);
    }

    /**
     * Lädt App-Konfiguration robust
     */
    private function loadAppConfig(?ConfigManager $configManager): array
    {
        if ($configManager !== null) {
            try {
                return $configManager->get('app/Config/app.php', fn() => $this->getDefaultConfig());
            } catch (\Throwable) {
            }
        }
        return $this->loadConfigDirectly();
    }

    /**
     * Direktes Config-Loading als Fallback
     */

    /**
     * Direktes Config-Loading als Fallback
     */
    private function loadConfigDirectly(): array
    {
        $configPath = __DIR__ . '/../../../app/Config/app.php';

        if (file_exists($configPath)) {
            try {
                $config = require $configPath;
                return is_array($config) ? $config : [];
            } catch (\Throwable $e) {
                error_log("TemplateConfigManager: Direct config loading failed: " . $e->getMessage());
            }
        }

        return $this->getDefaultConfig();
    }

    /**
     * Standard-Konfiguration als Fallback
     */
    private function getDefaultConfig(): array
    {
        return [
            'name' => 'KickersCup Manager',
            'version' => '2.0.0',
            'debug' => false,
            'locale' => 'de',
            'environment' => 'production'
        ];
    }

    /**
     * Debug-Modus ermitteln
     */
    public function isDebugMode(): bool
    {
        return (bool) ($this->config['debug'] ?? false);
    }

    /**
     * App-Name abrufen
     */
    public function getAppName(): string
    {
        return $this->config['name'] ?? 'KickersCup Manager';
    }

    /**
     * App-Version abrufen
     */
    public function getAppVersion(): string
    {
        return $this->config['version'] ?? '2.0.0';
    }

    /**
     * Locale abrufen
     */
    public function getLocale(): string
    {
        return $this->config['locale'] ?? 'de';
    }

    /**
     * Environment abrufen
     */
    public function getEnvironment(): string
    {
        return $this->config['environment'] ?? 'production';
    }

    /**
     * Komplette Config abrufen
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Config-Wert mit Fallback abrufen
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Debug-Informationen für Troubleshooting
     */
    public function getDebugInfo(): array
    {
        return [
            'config_loaded' => !empty($this->config),
            'debug_mode' => $this->isDebugMode(),
            'config_source' => $this->determineConfigSource(),
            'config_keys' => array_keys($this->config),
            'app_name' => $this->getAppName(),
            'app_version' => $this->getAppVersion(),
            'locale' => $this->getLocale(),
            'environment' => $this->getEnvironment(),
        ];
    }

    /**
     * Ermittelt die Quelle der Konfiguration
     */
    private function determineConfigSource(): string
    {
        if (empty($this->config)) {
            return 'default_fallback';
        }

        if ($this->config === $this->getDefaultConfig()) {
            return 'default_config';
        }

        return 'loaded_from_file';
    }
}