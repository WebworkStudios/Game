<?php

declare(strict_types=1);

namespace Framework\Core;

use RuntimeException;

/**
 * ConfigNotFoundException - Einheitliche Exception für fehlende Config-Dateien
 *
 * Eliminiert duplizierte Error-Messages zwischen ServiceProviders.
 */
class ConfigNotFoundException extends RuntimeException
{
    public function __construct(
        private readonly string $configPath,
        private readonly string $configName,
        ?\Throwable $previous = null
    ) {
        $message = $this->buildErrorMessage();
        parent::__construct($message, 0, $previous);
    }

    /**
     * Baut konsistente Error-Message für alle ServiceProvider
     */
    private function buildErrorMessage(): string
    {
        return sprintf(
            "Config file not found: %s\n" .
            "Please create this file or run: php artisan config:publish %s",
            $this->configPath,
            $this->configName
        );
    }

    /**
     * Gibt den Config-Pfad zurück
     */
    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    /**
     * Gibt den Config-Namen zurück
     */
    public function getConfigName(): string
    {
        return $this->configName;
    }

    /**
     * Prüft ob es sich um eine spezifische Config handelt
     */
    public function isConfig(string $configName): bool
    {
        return $this->configName === $configName;
    }
}