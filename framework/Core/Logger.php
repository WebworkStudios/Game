<?php

/**
 * Logger with Correlation IDs
 * Logging system with correlation ID support for request tracking
 *
 * File: framework/Core/Logger.php
 * Directory: /framework/Core/
 */

declare(strict_types=1);

namespace Framework\Core;

class Logger
{
    private array $config;
    private ?string $correlationId = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->generateCorrelationId();
    }

    /**
     * Generate correlation ID for request tracking
     */
    private function generateCorrelationId(): void
    {
        $this->correlationId = $_SERVER['HTTP_X_CORRELATION_ID'] ?? uniqid('req_', true);
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log message with level
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $channel = $this->config['default'] ?? 'daily';
        $channelConfig = $this->config['channels'][$channel] ?? [];

        $logLevel = $channelConfig['level'] ?? 'info';

        if (!$this->shouldLog($level, $logLevel)) {
            return;
        }

        $logEntry = $this->formatLogEntry($level, $message, $context);

        switch ($channelConfig['driver'] ?? 'daily') {
            case 'daily':
                $this->writeToDaily($logEntry, $channelConfig);
                break;
            case 'single':
                $this->writeToSingle($logEntry, $channelConfig);
                break;
            case 'syslog':
                $this->writeToSyslog($level, $logEntry);
                break;
        }
    }

    /**
     * Check if level should be logged
     */
    private function shouldLog(string $level, string $configLevel): bool
    {
        $levels = [
            'debug' => 0,
            'info' => 1,
            'warning' => 2,
            'error' => 3,
            'critical' => 4
        ];

        return ($levels[$level] ?? 1) >= ($levels[$configLevel] ?? 1);
    }

    /**
     * Format log entry
     */
    private function formatLogEntry(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $contextString = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);

        return "[$timestamp] [$levelUpper] [$this->correlationId] $message$contextString" . PHP_EOL;
    }

    /**
     * Write to daily log files
     */
    private function writeToDaily(string $logEntry, array $config): void
    {
        $logPath = $config['path'] ?? __DIR__ . '/../../logs/application.log';
        $logDir = dirname($logPath);
        $logFile = basename($logPath, '.log');
        $extension = pathinfo($logPath, PATHINFO_EXTENSION);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $dailyFile = $logDir . '/' . $logFile . '-' . date('Y-m-d') . '.' . $extension;

        file_put_contents($dailyFile, $logEntry, FILE_APPEND | LOCK_EX);

        if (isset($config['permission'])) {
            chmod($dailyFile, $config['permission']);
        }

        // Clean old log files
        $this->cleanOldLogs($logDir, $logFile, $extension, $config['days'] ?? 14);
    }

    /**
     * Clean old log files
     */
    private function cleanOldLogs(string $logDir, string $logFile, string $extension, int $days): void
    {
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        $pattern = $logDir . '/' . $logFile . '-*.{' . $extension . '}';

        foreach (glob($pattern, GLOB_BRACE) as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }

    /**
     * Write to single log file
     */
    private function writeToSingle(string $logEntry, array $config): void
    {
        $logPath = $config['path'] ?? __DIR__ . '/../../logs/application.log';
        $logDir = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Write to syslog
     */
    private function writeToSyslog(string $level, string $logEntry): void
    {
        $priority = match ($level) {
            'debug' => LOG_DEBUG,
            'warning' => LOG_WARNING,
            'error' => LOG_ERR,
            'critical' => LOG_CRIT,
            default => LOG_INFO
        };

        syslog($priority, $logEntry);
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log critical message
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Get current correlation ID
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    /**
     * Set correlation ID
     */
    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }
}