<?php
declare(strict_types=1);

namespace Framework\Cache\Drivers;

use Framework\Cache\CacheDriverInterface;

/**
 * FileCacheDriver - File System Implementation
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
            $cached = require $file;

            // Check TTL
            if (isset($cached['expires_at']) && $cached['expires_at'] < time()) {
                unlink($file);
                return null;
            }

            return $cached['data'] ?? null;
        } catch (\Throwable) {
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

        $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        return file_put_contents($file, $content, LOCK_EX) !== false;
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
        return file_exists($this->getCacheFile($key));
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