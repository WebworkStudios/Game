<?php
declare(strict_types=1);

/**
 * KickersCup Cache Status CLI Tool
 *
 * Usage: php cache-status.php
 */

// Bootstrap Framework minimal
require_once __DIR__ . '/vendor/autoload.php';

use Framework\Core\CacheDebugInfo;

// Einfache CLI-Ausgabe
function printCacheStatus(): void
{
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "   KickersCup Framework - Cache Status\n";
    echo str_repeat("=", 50) . "\n\n";

    try {
        // Hauptstatus
        echo "🎯 " . CacheDebugInfo::getSimpleStatus() . "\n\n";

        $status = CacheDebugInfo::getCurrentCacheStatus();

        // Verfügbare Driver
        echo "🔧 Available Cache Drivers:\n";
        foreach ($status['available_drivers'] as $driver => $info) {
            $emoji = $info['available'] ? '✅' : '❌';
            $paddedDriver = str_pad(strtoupper($driver), 10);
            echo "   {$emoji} {$paddedDriver} → {$info['status']}\n";
        }

        // Performance Info
        echo "\n⚡ Performance (Active Driver):\n";
        $perf = $status['performance_info'];
        echo "   Read Speed:  {$perf['read_speed']}\n";
        echo "   Write Speed: {$perf['write_speed']}\n";
        echo "   Speedup:     {$perf['relative_speed']}\n";

        // Memory Info (nur bei APCu)
        if ($status['active_driver'] === 'apcu' && isset($status['memory_usage']['total_memory'])) {
            echo "\n💾 APCu Memory Usage:\n";
            $mem = $status['memory_usage'];
            echo "   Total:       {$mem['total_memory']}\n";
            echo "   Used:        {$mem['used_memory']} ({$mem['usage_percentage']})\n";
            echo "   Available:   {$mem['available_memory']}\n";
            echo "   Cache Hits:  " . number_format($mem['cache_hits']) . "\n";
            echo "   Cache Miss:  " . number_format($mem['cache_misses']) . "\n";
        }

        // Empfehlungen
        if (!empty($status['recommendations'])) {
            echo "\n💡 Recommendations:\n";
            foreach ($status['recommendations'] as $rec) {
                echo "   • {$rec}\n";
            }
        }

    } catch (Throwable $e) {
        echo "❌ Error: {$e->getMessage()}\n";
        echo "   Make sure the framework is properly installed.\n";
    }

    echo "\n" . str_repeat("-", 50) . "\n";
    echo "Run 'php cache-status.php' anytime to check cache status\n";
    echo str_repeat("-", 50) . "\n\n";
}

// Nur ausführen wenn direkt aufgerufen (nicht bei require/include)
if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    printCacheStatus();
}
