<?php
/**
 * Windows Cache Permission Fix
 *
 * Speichere als: fix_windows_cache.php
 * Ausführen: php fix_windows_cache.php
 */

declare(strict_types=1);

echo "🔧 Windows Cache Permission Fix\n";
echo "=" . str_repeat("=", 40) . "\n\n";

$cacheDir = __DIR__ . '/storage/cache/views';

try {
    // 1. Cache Directory neu erstellen
    echo "1. Recreating cache directory:\n";

    if (is_dir($cacheDir)) {
        echo "Removing existing cache directory...\n";

        // Windows-kompatible Verzeichnis-Löschung
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($cacheDir);
        echo "✅ Cache directory removed\n";
    }

    // Neu erstellen mit korrekten Permissions
    if (mkdir($cacheDir, 0755, true)) {
        echo "✅ Cache directory created: {$cacheDir}\n";
    } else {
        echo "❌ Failed to create cache directory\n";
    }

    // 2. Test file operations
    echo "\n2. Testing file operations:\n";

    $testFile = $cacheDir . '/test_' . time() . '.php';
    $testContent = "<?php\n\nreturn ['test' => 'data'];\n";

    // Test write
    if (file_put_contents($testFile, $testContent) !== false) {
        echo "✅ File write: OK\n";

        // Test read
        $readContent = file_get_contents($testFile);
        if ($readContent !== false) {
            echo "✅ File read: OK\n";

            // Test include
            try {
                $data = include $testFile;
                if (is_array($data) && isset($data['test'])) {
                    echo "✅ File include: OK\n";
                } else {
                    echo "❌ File include: Invalid data\n";
                }
            } catch (\Throwable $e) {
                echo "❌ File include: " . $e->getMessage() . "\n";
            }
        } else {
            echo "❌ File read: Failed\n";
        }

        // Cleanup
        unlink($testFile);
        echo "✅ Test file cleanup: OK\n";

    } else {
        echo "❌ File write: Failed\n";
    }

    // 3. Fix TemplateCache für Windows
    echo "\n3. Testing TemplateCache:\n";

    try {
        $templateCache = \Framework\Templating\TemplateCache::create($cacheDir, true);
        echo "✅ TemplateCache created\n";

        // Test operations
        $testTemplate = 'windows_test_' . time();
        $testData = ['test' => 'windows_data'];

        $templateCache->store($testTemplate, '/test/path', $testData);
        echo "✅ Cache store: OK\n";

        $loaded = $templateCache->load($testTemplate);
        if ($loaded !== null) {
            echo "✅ Cache load: OK\n";
        } else {
            echo "❌ Cache load: Failed\n";
        }

    } catch (\Throwable $e) {
        echo "❌ TemplateCache: " . $e->getMessage() . "\n";
    }

    // 4. Windows-spezifische Cache-Klasse
    echo "\n4. Creating Windows-optimized cache:\n";

    $windowsCacheFile = __DIR__ . '/framework/Templating/WindowsTemplateCache.php';
    $windowsCacheCode = '<?php
declare(strict_types=1);

namespace Framework\Templating;

/**
 * Windows-optimized TemplateCache
 */
class WindowsTemplateCache extends TemplateCache
{
    /**
     * Windows-safe file operations
     */
    protected function safeFileWrite(string $file, string $content): bool
    {
        // Ensure directory exists
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Windows-safe write with lock
        $tempFile = $file . \'.tmp\' . getmypid();
        
        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            return false;
        }
        
        // Atomic rename on Windows
        if (!rename($tempFile, $file)) {
            unlink($tempFile);
            return false;
        }
        
        return true;
    }
    
    /**
     * Windows-safe file read
     */
    protected function safeFileRead(string $file): ?string
    {
        if (!file_exists($file) || !is_readable($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        return $content !== false ? $content : null;
    }
}';

    if (!file_exists($windowsCacheFile)) {
        file_put_contents($windowsCacheFile, $windowsCacheCode);
        echo "✅ Windows cache class created\n";
    } else {
        echo "ℹ️  Windows cache class already exists\n";
    }

} catch (\Throwable $e) {
    echo "💥 CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n🎯 NEXT STEPS:\n";
echo "1. Run this fix script\n";
echo "2. Disable cache in templating.php\n";
echo "3. Test your application\n";
echo "4. Re-enable cache after confirmation\n";

echo "\n🏁 Windows cache fix completed!\n";
?>