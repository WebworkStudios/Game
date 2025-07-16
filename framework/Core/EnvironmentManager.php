<?php

declare(strict_types=1);

namespace Framework\Core;

/**
 * EnvironmentManager - Verwaltet PHP Environment Setup
 *
 * Verantwortlichkeiten:
 * - Error Reporting konfigurieren
 * - Timezone setzen
 * - Charset konfigurieren
 * - Memory Limits (optional)
 */
class EnvironmentManager
{
    private const string DEFAULT_TIMEZONE = 'UTC';
    private const string DEFAULT_CHARSET = 'UTF-8';

    /**
     * Setup der PHP-Umgebung
     *
     * @param array|null $config Optional: Config-Array mit timezone, charset, etc.
     */
    public function setup(?array $config = null): void
    {
        $this->setupErrorReporting();
        $this->setupTimezone($config['timezone'] ?? self::DEFAULT_TIMEZONE);
        $this->setupCharset($config['charset'] ?? self::DEFAULT_CHARSET);

        // Optional: Memory Limits aus Config
        if (isset($config['memory_limit'])) {
            $this->setupMemoryLimit($config['memory_limit']);
        }
    }

    /**
     * Konfiguriert Error Reporting für Development/Production
     */
    private function setupErrorReporting(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    }

    /**
     * Setzt die Zeitzone
     */
    private function setupTimezone(string $timezone): void
    {
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new \InvalidArgumentException("Invalid timezone: {$timezone}");
        }

        date_default_timezone_set($timezone);
    }

    /**
     * Konfiguriert Charset
     */
    private function setupCharset(string $charset): void
    {
        ini_set('default_charset', $charset);
        mb_internal_encoding($charset);
    }

    /**
     * Setzt Memory Limit (optional)
     */
    private function setupMemoryLimit(string $memoryLimit): void
    {
        ini_set('memory_limit', $memoryLimit);
    }
}