<?php
declare(strict_types=1);

namespace Framework\Cache\Drivers;

use Framework\Cache\CacheDriverInterface;

/**
 * FileCacheDriver - File System Implementation
 *
 * GEFIXT: Serialization statt var_export für robuste Cache-Speicherung
 * Löst das Problem mit weißen Seiten beim Cache-Refresh
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
            // GEFIXT: Robuste Cache-Validierung vor dem Laden
            if (!$this->validateCacheFile($file)) {
                error_log("Corrupted cache file detected, removing: {$file}");
                @unlink($file);
                return null;
            }

            $cached = require $file;

            // Check TTL
            if (isset($cached['expires_at']) && $cached['expires_at'] < time()) {
                unlink($file);
                return null;
            }

            return $cached['data'] ?? null;

        } catch (\Throwable $e) {
            error_log("Cache file loading error: " . $e->getMessage());

            // Cleanup corrupted cache file
            @unlink($file);
            return null;
        }
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getCacheFile($key);
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'data' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];

        try {
            // KRITISCHER FIX: Serialization statt var_export
            // var_export() kann bei komplexen Objekten fehlschlagen
            $serialized = serialize($data);

            // Robuste Content-Generierung
            $content = $this->generateCacheContent($serialized);

            // Atomic write mit Temp-Datei
            $tempFile = $file . '.tmp';

            if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
                return false;
            }

            // Validierung vor dem finalen Move
            if (!$this->validateCacheFile($tempFile)) {
                @unlink($tempFile);
                error_log("Generated cache file failed validation");
                return false;
            }

            // Atomic move
            if (!rename($tempFile, $file)) {
                @unlink($tempFile);
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            error_log("Cache put error: " . $e->getMessage());
            return false;
        }
    }

    public function forget(string $key): bool
    {
        $file = $this->getCacheFile($key);
        return file_exists($file) ? unlink($file) : true;
    }

    public function flush(): bool
    {
        $files = glob($this->cacheDir . '/**/*.php');
        if ($files === false) return true;

        $success = true;
        foreach ($files as $file) {
            $success = unlink($file) && $success;
        }
        return $success;
    }

    public function exists(string $key): bool
    {
        $file = $this->getCacheFile($key);
        return file_exists($file) && $this->validateCacheFile($file);
    }

    /**
     * NEU: Robuste Cache-Content-Generierung
     */
    private function generateCacheContent(string $serializedData): string
    {
        // Escaping für sichere String-Einbettung
        $escapedData = var_export($serializedData, true);

        return <<<PHP
<?php

declare(strict_types=1);

// Cache file generated at: {date('Y-m-d H:i:s')}
// Framework: KickersCup Manager
// DO NOT EDIT - This file is auto-generated

return unserialize({$escapedData});

PHP;
    }

    /**
     * NEU: Cache-Datei-Validierung
     */
    private function validateCacheFile(string $file): bool
    {
        try {
            // Test ob Datei parseable ist
            $result = require $file;

            // Muss Array sein
            if (!is_array($result)) {
                return false;
            }

            // Muss erforderliche Felder haben
            if (!isset($result['data'], $result['expires_at'], $result['created_at'])) {
                return false;
            }

            // Timestamp-Validierung
            if (!is_int($result['expires_at']) || !is_int($result['created_at'])) {
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            error_log("Cache file validation failed: " . $e->getMessage());
            return false;
        }
    }

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getCacheFile(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . '/' . substr($hash, 0, 2) . '/' . $hash . '.php';
    }
}