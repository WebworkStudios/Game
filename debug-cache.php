<?php
/**
 * Erweiterte Cache-Diagnose für KickersCup Manager
 * Speichern als: enhanced_debug.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== KICKERSCUP ERWEITERTE CACHE DIAGNOSE ===\n\n";

// Autoloader einbinden (falls verfügbar)
$autoloaderPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/autoload.php',
    __DIR__ . '/bootstrap.php'
];

foreach ($autoloaderPaths as $path) {
    if (file_exists($path)) {
        echo "Loading autoloader: {$path}\n";
        require_once $path;
        break;
    }
}

// 1. FRAMEWORK STRUCTURE CHECK
echo "\n1. FRAMEWORK STRUCTURE:\n";
$frameworkFiles = [
    'framework/Routing/RouterCache.php',
    'framework/Cache/Drivers/FileCacheDriver.php',
    'framework/Cache/CacheDriverInterface.php',
    'framework/Routing/RouteEntry.php',
    'framework/Http/HttpMethod.php',
    'app/Actions',
];

foreach ($frameworkFiles as $file) {
    $exists = file_exists($file) || is_dir($file);
    echo "- {$file}: " . ($exists ? "✓" : "✗") . "\n";
}

// 2. KLASSEN VERFÜGBARKEIT
echo "\n2. KLASSEN VERFÜGBARKEIT:\n";
$requiredClasses = [
    'Framework\\Routing\\RouterCache',
    'Framework\\Cache\\Drivers\\FileCacheDriver',
    'Framework\\Routing\\RouteEntry',
    'Framework\\Http\\HttpMethod',
];

foreach ($requiredClasses as $class) {
    $exists = class_exists($class);
    echo "- {$class}: " . ($exists ? "✓" : "✗") . "\n";

    if (!$exists) {
        // Try to load manually
        $classFile = str_replace(['Framework\\', '\\'], ['framework/', '/'], $class) . '.php';
        if (file_exists($classFile)) {
            echo "  -> Trying to load: {$classFile}\n";
            try {
                require_once $classFile;
                echo "  -> " . (class_exists($class) ? "✓ Loaded" : "✗ Failed") . "\n";
            } catch (\Throwable $e) {
                echo "  -> ✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }
}

// 3. SIMULATE ACTUAL APPLICATION FLOW
echo "\n3. ECHTE APPLICATION FLOW SIMULATION:\n";

try {
    // Clear existing cache
    $cacheFile = 'storage/cache/routes.php';
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
        echo "✓ Cache cleared\n";
    }

    // Manual RouterCache instantiation
    if (class_exists('Framework\\Routing\\RouterCache')) {
        echo "Creating RouterCache...\n";
        $routerCache = new Framework\Routing\RouterCache($cacheFile, 'app/Actions');

        // FIRST REQUEST SIMULATION
        echo "\n--- FIRST REQUEST SIMULATION ---\n";
        $start = microtime(true);
        $routes1 = $routerCache->loadRouteEntries();
        $time1 = microtime(true) - $start;

        echo "Routes loaded: " . count($routes1) . "\n";
        echo "Time taken: " . round($time1 * 1000, 2) . "ms\n";
        echo "Cache file exists: " . (file_exists($cacheFile) ? "YES" : "NO") . "\n";

        if (file_exists($cacheFile)) {
            echo "Cache file size: " . filesize($cacheFile) . " bytes\n";

            // Check cache file content
            $content = file_get_contents($cacheFile);
            echo "Content preview (first 300 chars):\n";
            echo substr($content, 0, 300) . "...\n";
        }

        // SECOND REQUEST SIMULATION (this is where it might fail)
        echo "\n--- SECOND REQUEST SIMULATION ---\n";

        // Create new RouterCache instance (simulates new request)
        $routerCache2 = new Framework\Routing\RouterCache($cacheFile, 'app/Actions');

        $start = microtime(true);
        $routes2 = $routerCache2->loadRouteEntries();
        $time2 = microtime(true) - $start;

        echo "Routes loaded: " . count($routes2) . "\n";
        echo "Time taken: " . round($time2 * 1000, 2) . "ms\n";
        echo "Same count as first: " . (count($routes1) === count($routes2) ? "YES" : "NO") . "\n";

        // THIRD REQUEST SIMULATION
        echo "\n--- THIRD REQUEST SIMULATION ---\n";
        $routerCache3 = new Framework\Routing\RouterCache($cacheFile, 'app/Actions');

        $start = microtime(true);
        $routes3 = $routerCache3->loadRouteEntries();
        $time3 = microtime(true) - $start;

        echo "Routes loaded: " . count($routes3) . "\n";
        echo "Time taken: " . round($time3 * 1000, 2) . "ms\n";

    } else {
        echo "✗ RouterCache class not available\n";
    }

} catch (\Throwable $e) {
    echo "✗ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// 4. CACHE FILE DEEP ANALYSIS
echo "\n4. CACHE FILE DEEP ANALYSIS:\n";
if (file_exists($cacheFile)) {
    echo "Analyzing cache file content...\n";

    // Test multiple require attempts
    for ($i = 1; $i <= 3; $i++) {
        echo "Require attempt #{$i}: ";
        try {
            $result = require $cacheFile;
            echo "✓ SUCCESS (returned " . gettype($result) . ")\n";

            if (is_array($result)) {
                echo "  Array count: " . count($result) . "\n";
            }

        } catch (\Throwable $e) {
            echo "✗ FAILED: " . $e->getMessage() . "\n";
        }
    }

    // Check for syntax errors
    echo "PHP Syntax check: ";
    $output = [];
    $returnCode = 0;
    exec("php -l \"{$cacheFile}\" 2>&1", $output, $returnCode);

    if ($returnCode === 0) {
        echo "✓ VALID\n";
    } else {
        echo "✗ SYNTAX ERROR\n";
        echo "Output: " . implode("\n", $output) . "\n";
    }
}

// 5. FILE PERMISSIONS AND SYSTEM INFO
echo "\n5. SYSTEM INFO:\n";
echo "PHP SAPI: " . php_sapi_name() . "\n";
echo "Operating System: " . PHP_OS . "\n";
echo "PHP User: " . get_current_user() . "\n";

if (file_exists($cacheFile)) {
    $perms = fileperms($cacheFile);
    echo "Cache file permissions: " . decoct($perms & 0777) . "\n";
    echo "Cache file owner: " . fileowner($cacheFile) . "\n";
    echo "Cache file readable: " . (is_readable($cacheFile) ? "YES" : "NO") . "\n";
    echo "Cache file writable: " . (is_writable($cacheFile) ? "YES" : "NO") . "\n";
}

// 6. TEMPLATE CACHE TEST
echo "\n6. TEMPLATE CACHE TEST:\n";
if (class_exists('Framework\\Cache\\Drivers\\FileCacheDriver')) {
    try {
        $templateCache = new Framework\Cache\Drivers\FileCacheDriver('storage/cache/data');

        // Test with complex data similar to templates
        $complexData = [
            'version' => '2.1',
            'compiled_at' => time(),
            'template_path' => '/path/to/template.html',
            'data' => [
                'tokens' => [
                    ['type' => 'text', 'content' => 'Hello'],
                    ['type' => 'variable', 'name' => 'user.name'],
                    ['type' => 'control', 'structure' => 'if', 'condition' => 'user.active']
                ],
                'blocks' => ['content', 'sidebar'],
                'parent' => null
            ],
            'checksum' => md5('test'),
            'php_version' => PHP_VERSION
        ];

        $testKey = 'debug_template_test';

        echo "Testing FileCacheDriver with complex template-like data...\n";
        $putResult = $templateCache->put($testKey, $complexData, 3600);
        echo "PUT: " . ($putResult ? "✓" : "✗") . "\n";

        $getData = $templateCache->get($testKey);
        $match = $getData === $complexData;
        echo "GET: " . ($match ? "✓" : "✗") . "\n";

        if (!$match) {
            echo "Data mismatch details:\n";
            echo "Original keys: " . implode(', ', array_keys($complexData)) . "\n";
            echo "Retrieved keys: " . implode(', ', array_keys($getData ?: [])) . "\n";
        }

        $templateCache->forget($testKey);

    } catch (\Throwable $e) {
        echo "✗ Template cache error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== DIAGNOSE ABGESCHLOSSEN ===\n";
echo "Bitte teilen Sie diese komplette Ausgabe mit!\n";