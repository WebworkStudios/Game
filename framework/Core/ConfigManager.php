<?php

declare(strict_types=1);

namespace Framework\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * Config Manager - Zentrale Konfigurationsverwaltung für das Framework
 *
 * Eliminiert Code-Duplikation zwischen Service Providern und bietet:
 * - Lazy Loading mit Memory-Caching
 * - Auto-Publishing von Default-Konfigurationen
 * - Konsistente Fehlerbehandlung
 * - Validation von Config-Strukturen
 */
class ConfigManager
{
    /**
     * Cache für geladene Konfigurationen
     * @var array<string, array>
     */
    private static array $cache = [];

    public function __construct(
        private readonly string $basePath
    )
    {
    }

    /**
     * Leert den gesamten Config-Cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Leert Cache für spezifische Config
     */
    public static function clearCacheFor(string $configPath): void
    {
        unset(self::$cache[$configPath]);
    }

    /**
     * Holt alle gecachten Config-Pfade (für Debugging)
     */
    public static function getCachedPaths(): array
    {
        return array_keys(self::$cache);
    }

    /**
     * Überprüft ob eine Konfiguration existiert (Datei oder Cache)
     */
    public function exists(string $configPath): bool
    {
        return isset(self::$cache[$configPath]) || file_exists($this->buildFullPath($configPath));
    }

    /**
     * Lädt Konfiguration neu (Cache-Bypass)
     */
    public function reload(string $configPath): array
    {
        // Cache leeren
        unset(self::$cache[$configPath]);

        // Neu laden ohne Default Provider (nur existierende Dateien)
        $fullPath = $this->buildFullPath($configPath);

        if (!file_exists($fullPath)) {
            throw new RuntimeException("Cannot reload non-existent config: {$configPath}");
        }

        return $this->get($configPath);
    }

    /**
     * Lädt Konfiguration mit Lazy Loading und Auto-Publishing
     *
     * @param string $configPath Relativer Pfad zur Config-Datei (z.B. 'app/Config/database.php')
     * @param callable|null $defaultProvider Optional: Factory für Default-Config
     * @param array $requiredKeys Optional: Validation der erforderlichen Keys
     * @return array Geladene Konfiguration
     *
     * @throws InvalidArgumentException Bei fehlenden Required Keys
     * @throws RuntimeException Bei File-System Fehlern
     */
    public function get(string $configPath, ?callable $defaultProvider = null, array $requiredKeys = []): array
    {
        // 1. Cache prüfen (Lazy Loading)
        if (isset(self::$cache[$configPath])) {
            return self::$cache[$configPath];
        }

        $fullPath = $this->buildFullPath($configPath);

        // 2. Config-Datei laden falls vorhanden
        if (file_exists($fullPath)) {
            $config = $this->loadConfigFile($fullPath);
        } // 3. Default-Config erstellen falls Provider vorhanden
        elseif ($defaultProvider !== null) {
            $config = $this->createDefaultConfig($configPath, $defaultProvider);
        } // 4. Fehler falls keine Config und kein Default Provider
        else {
            throw new RuntimeException("Config file not found and no default provider given: {$configPath}");
        }

        // 5. Required Keys validieren
        $this->validateRequiredKeys($config, $requiredKeys, $configPath);

        // 6. Cache speichern
        self::$cache[$configPath] = $config;

        return $config;
    }

    /**
     * Lädt und validiert Config-Datei
     */
    private function loadConfigFile(string $fullPath): array
    {
        try {
            $config = require $fullPath;

            if (!is_array($config)) {
                throw new InvalidArgumentException("Config file must return array: {$fullPath}");
            }

            return $config;

        } catch (\ParseError $e) {
            throw new RuntimeException("Syntax error in config file {$fullPath}: " . $e->getMessage());
        } catch (\Error $e) {
            throw new RuntimeException("Error loading config file {$fullPath}: " . $e->getMessage());
        }
    }

    /**
     * Erstellt Default-Config und publiziert sie
     */
    private function createDefaultConfig(string $configPath, callable $defaultProvider): array
    {
        // Default-Config generieren
        $defaultConfig = $defaultProvider();

        if (!is_array($defaultConfig)) {
            throw new InvalidArgumentException('Default provider must return array');
        }

        // Als PHP-Array-String formatieren für Publishing
        $contentProvider = function () use ($defaultConfig) {
            return $this->generateConfigFileContent($defaultConfig);
        };

        // Config-Datei erstellen
        if (!$this->publish($configPath, $contentProvider)) {
            throw new RuntimeException("Failed to create default config: {$configPath}");
        }

        return $defaultConfig;
    }

    /**
     * Generiert PHP-Config-Datei-Inhalt aus Array
     */
    private function generateConfigFileContent(array $config): string
    {
        $exportedConfig = var_export($config, true);

        return <<<PHP
<?php

declare(strict_types=1);

// Auto-generated configuration file
// Created: {date('Y-m-d H:i:s')}
// You can modify this file according to your needs

return {$exportedConfig};
PHP;
    }

    /**
     * Publiziert eine Default-Konfiguration
     *
     * @param string $configPath Relativer Pfad zur Config-Datei
     * @param callable $contentProvider Factory die den Config-Inhalt als String zurückgibt
     * @return bool True bei Erfolg, false bei Fehler
     */
    public function publish(string $configPath, callable $contentProvider): bool
    {
        $fullPath = $this->buildFullPath($configPath);
        $configDir = dirname($fullPath);

        // Verzeichnisstruktur erstellen
        if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
            return false;
        }

        // Config-Content generieren
        $content = $contentProvider();

        if (!is_string($content)) {
            throw new InvalidArgumentException('Content provider must return string');
        }

        // Datei schreiben
        $success = file_put_contents($fullPath, $content) !== false;

        // Cache invalidieren bei erfolgreichem Schreiben
        if ($success && isset(self::$cache[$configPath])) {
            unset(self::$cache[$configPath]);
        }

        return $success;
    }

    /**
     * Baut vollständigen Dateipfad
     */
    private function buildFullPath(string $configPath): string
    {
        return $this->basePath . '/' . ltrim($configPath, '/');
    }

    /**
     * Validiert Required Keys in Konfiguration
     */
    private function validateRequiredKeys(array $config, array $requiredKeys, string $configPath): void
    {
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                throw new InvalidArgumentException("Missing required config key '{$key}' in: {$configPath}");
            }
        }
    }
}