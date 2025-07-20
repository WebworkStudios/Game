<?php
declare(strict_types=1);

namespace Framework\Cache\Drivers;

use Framework\Cache\CacheDriverInterface;

/**
 * FileCacheDriver - GEFIXT: Template Caching Problem behoben
 *
 * PROBLEM BEHOBEN:
 * ✅ Doppelte Serialisierung eliminiert
 * ✅ Robuste Cache-Validierung
 * ✅ Atomic File Operations
 * ✅ Corruption Recovery
 * ✅ Emergency Fallbacks
 */
readonly class FileCacheDriver implements CacheDriverInterface
{
    public function __construct(
        private string $cacheDir
    ) {
        $this->ensureCacheDirectory();
    }

    public function get(string $key): mixed
    {
        $file = $this->getCacheFile($key);

        if (!file_exists($file)) {
            return null;
        }

        try {
            // ROBUST: Cache-Validierung vor dem Laden
            if (!$this->validateCacheFile($file)) {
                @unlink($file);
                return null;
            }

            // GEFIXT: Direktes require ohne Deserialisierung
            $cached = require $file;

            // Structure validation
            if (!is_array($cached) || !isset($cached['data'], $cached['expires_at'])) {
                @unlink($file);
                return null;
            }

            // TTL Check
            if ($cached['expires_at'] < time()) {
                @unlink($file);
                return null;
            }

            return $cached['data'];

        } catch (\Throwable $e) {
            @unlink($file);
            return null;
        }
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getCacheFile($key);
        $dir = dirname($file);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return false;
            }
        }

        $data = [
            'data' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time(),
            'cache_version' => '2.1'
        ];

        try {
            // GEFIXT: Einfache var_export Strategie ohne doppelte Serialisierung
            $content = $this->generateSafeContent($data);

            // Atomic write mit Temp-Datei
            $tempFile = $file . '.tmp.' . uniqid();

            if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
                return false;
            }

            // Validierung der generierten Datei
            if (!$this->validateGeneratedFile($tempFile)) {
                @unlink($tempFile);
                return false;
            }

            // Atomic move
            if (!rename($tempFile, $file)) {
                @unlink($tempFile);
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            if (isset($tempFile)) {
                @unlink($tempFile);
            }
            return false;
        }
    }

    public function forget(string $key): bool
    {
        $file = $this->getCacheFile($key);
        return file_exists($file) ? @unlink($file) : true;
    }

    public function flush(): bool
    {
        $pattern = $this->cacheDir . '/**/*.php';
        $files = glob($pattern, GLOB_BRACE);

        if ($files === false) {
            return true;
        }

        $success = true;
        foreach ($files as $file) {
            if (!@unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    public function exists(string $key): bool
    {
        $file = $this->getCacheFile($key);
        return file_exists($file) && $this->validateCacheFile($file);
    }

    /**
     * KRITISCHER FIX: Sichere Content-Generierung ohne Serialisierung
     */
    private function generateSafeContent(array $data): string
    {
        try {
            // GEFIXT: Direkte var_export ohne Serialisierung
            $exportedData = var_export($data, true);

            // Validierung des exportierten Contents
            if (empty($exportedData) || $exportedData === 'NULL') {
                throw new \RuntimeException("var_export failed for cache data");
            }

            $timestamp = date('Y-m-d H:i:s');

            return <<<PHP
<?php

declare(strict_types=1);

/**
 * KickersCup Manager - Cache File
 * Generated: {$timestamp}
 * DO NOT EDIT - Auto-generated content
 */

return {$exportedData};

PHP;

        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to generate cache content: " . $e->getMessage());
        }
    }

    /**
     * ROBUST: Cache-Datei-Validierung
     */
    private function validateCacheFile(string $file): bool
    {
        try {
            // Prüfung ob Datei lesbar ist
            if (!is_readable($file)) {
                return false;
            }

            // Prüfung der Dateigröße (nicht zu groß, nicht leer)
            $size = filesize($file);
            if ($size === false || $size === 0 || $size > 10485760) { // 10MB limit
                return false;
            }

            // Syntax-Check durch include
            $result = $this->testIncludeFile($file);

            return $result !== false;

        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * SAFE: Test-Include einer Cache-Datei
     */
    private function testIncludeFile(string $file): mixed
    {
        try {
            // Temporärer Error Handler für include
            set_error_handler(function($severity, $message, $file, $line) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            });

            $result = include $file;

            restore_error_handler();

            return $result;

        } catch (\Throwable $e) {
            restore_error_handler();
            return false;
        }
    }

    /**
     * VALIDATION: Generierte Datei vor dem finalen Move prüfen
     */
    private function validateGeneratedFile(string $file): bool
    {
        try {
            $result = $this->testIncludeFile($file);

            if (!is_array($result)) {
                return false;
            }

            // Struktur-Validierung
            $requiredKeys = ['data', 'expires_at', 'created_at'];
            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $result)) {
                    return false;
                }
            }

            // Type validation
            if (!is_int($result['expires_at']) || !is_int($result['created_at'])) {
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                throw new \RuntimeException("Cannot create cache directory: {$this->cacheDir}");
            }
        }
    }

    private function getCacheFile(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . '/' . substr($hash, 0, 2) . '/' . $hash . '.php';
    }
}