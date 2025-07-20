<?php
/**
 * Quick Fix Test - Speichern als: quick_fix_test.php
 *
 * Testet verschiedene Autoloader-Lösungen
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Quick Fix Test</h1>\n";

// Test 1: Composer Autoloader Inspektion
echo "<h2>1. Composer Autoloader Analysis</h2>\n";

$composerFile = __DIR__ . '/composer.json';
if (file_exists($composerFile)) {
    $composer = json_decode(file_get_contents($composerFile), true);
    echo "<p>composer.json exists</p>\n";
    echo "<p>Autoload config: " . json_encode($composer['autoload'] ?? 'not set') . "</p>\n";
} else {
    echo "<p>❌ composer.json missing!</p>\n";
}

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    echo "<p>✅ vendor/autoload.php exists</p>\n";
} else {
    echo "<p>❌ vendor/autoload.php missing!</p>\n";
}

// Test 2: Manual Class Loading
echo "<h2>2. Manual Class Loading Test</h2>\n";

$testClasses = [
    'Framework\\Routing\\RouterCache' => 'framework/Routing/RouterCache.php',
    'Framework\\Cache\\Drivers\\FileCacheDriver' => 'framework/Cache/Drivers/FileCacheDriver.php',
    'Framework\\Core\\ApplicationKernel' => 'framework/Core/ApplicationKernel.php'
];

foreach ($testClasses as $className => $filePath) {
    echo "<p>Testing {$className}:</p>\n";

    if (file_exists($filePath)) {
        echo "  ✅ File exists: {$filePath}<br>\n";

        try {
            require_once $filePath;
            echo "  ✅ File loaded successfully<br>\n";

            if (class_exists($className)) {
                echo "  ✅ Class available<br>\n";
            } else {
                echo "  ❌ Class not available after loading<br>\n";
            }
        } catch (\Throwable $e) {
            echo "  ❌ Error loading: " . $e->getMessage() . "<br>\n";
        }
    } else {
        echo "  ❌ File missing: {$filePath}<br>\n";
    }
}

// Test 3: Dependencies Check
echo "<h2>3. Dependencies Check</h2>\n";

// Check if RouterCache needs specific dependencies
if (class_exists('Framework\\Routing\\RouterCache')) {
    echo "<p>RouterCache available, checking dependencies...</p>\n";

    $dependencies = [
        'Framework\\Http\\HttpMethod',
        'Framework\\Routing\\RouteEntry',
        'Framework\\Routing\\Route'
    ];

    foreach ($dependencies as $dep) {
        $available = class_exists($dep) || interface_exists($dep) || enum_exists($dep);
        echo "<p>{$dep}: " . ($available ? "✅" : "❌") . "</p>\n";

        if (!$available) {
            // Try to find and load the file
            $depFile = str_replace(['Framework\\', '\\'], ['framework/', '/'], $dep) . '.php';
            if (file_exists($depFile)) {
                echo "  → Found file: {$depFile}, loading...<br>\n";
                try {
                    require_once $depFile;
                    $nowAvailable = class_exists($dep) || interface_exists($dep) || enum_exists($dep);
                    echo "  → " . ($nowAvailable ? "✅ Loaded" : "❌ Still not available") . "<br>\n";
                } catch (\Throwable $e) {
                    echo "  → ❌ Load error: " . $e->getMessage() . "<br>\n";
                }
            }
        }
    }
}

// Test 4: Try to create RouterCache
echo "<h2>4. RouterCache Creation Test</h2>\n";

try {
    if (class_exists('Framework\\Routing\\RouterCache')) {
        $routerCache = new Framework\Routing\RouterCache(
            'storage/cache/routes.php',
            'app/Actions'
        );
        echo "<p>✅ RouterCache created successfully</p>\n";

        // Test loadRouteEntries
        echo "<p>Testing loadRouteEntries...</p>\n";
        $routes = $routerCache->loadRouteEntries();
        echo "<p>✅ Routes loaded: " . count($routes) . "</p>\n";

    } else {
        echo "<p>❌ RouterCache class not available</p>\n";
    }
} catch (\Throwable $e) {
    echo "<p>❌ RouterCache creation failed: " . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . "</p>\n";
    echo "<p>Line: " . $e->getLine() . "</p>\n";
}

echo "<h2>✅ Test Complete</h2>\n";