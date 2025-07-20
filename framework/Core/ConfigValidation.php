<?php
declare(strict_types=1);

namespace Framework\Core;

/**
 * ConfigValidation Trait - Eliminiert Code-Duplikation in ServiceProviders
 *
 * Stellt einheitliche Config-Validierung für alle ServiceProvider zur Verfügung.
 * Eliminiert das duplizierte Config-Loading-Pattern.
 */
trait ConfigValidation
{
    /**
     * Prüft ob Config-Datei existiert und wirft konsistente Exception
     *
     * @param string $configName Name der Config ohne .php (z.B. 'database', 'security')
     * @throws ConfigNotFoundException Wenn Config-Datei nicht existiert
     */
    protected function ensureConfigExists(string $configName): void
    {
        if (!$this->configExists($configName)) {
            $configPath = "app/Config/{$configName}.php";
            throw new ConfigNotFoundException($configPath, $configName);
        }
    }

    /**
     * Prüft ob Config-Datei existiert (boolean return)
     *
     * @param string $configName Name der Config ohne .php
     * @return bool True wenn Config existiert
     */
    protected function configExists(string $configName): bool
    {
        // Verwendet AbstractServiceProvider::basePath() Methode
        if (!method_exists($this, 'basePath')) {
            throw new \BadMethodCallException(
                'ConfigValidation trait requires AbstractServiceProvider as base class'
            );
        }

        $configPath = "app/Config/{$configName}.php";
        return file_exists($this->basePath($configPath));
    }

    /**
     * Lädt Config und prüft Required Keys in einem Zug
     *
     * @param string $configName Name der Config ohne .php
     * @param array $requiredKeys Optional: Required Config Keys
     * @param callable|null $defaultProvider Optional: Factory für Default-Config
     * @return array Geladene Konfiguration
     * @throws ConfigNotFoundException Wenn Config nicht existiert
     * @throws \InvalidArgumentException Bei fehlenden Required Keys
     */
    protected function loadAndValidateConfig(string $configName, array $requiredKeys = [], ?callable $defaultProvider = null): array
    {
        // Verwendet AbstractServiceProvider::getConfig() Methode
        if (!method_exists($this, 'getConfig')) {
            throw new \BadMethodCallException(
                'ConfigValidation trait requires AbstractServiceProvider as base class'
            );
        }

        $configPath = "app/Config/{$configName}.php";

        // Config laden über ConfigManager (mit allen Features wie Caching, etc.)
        $config = $this->getConfig($configPath, $defaultProvider, $requiredKeys);

        return $config;
    }
}