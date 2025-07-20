<?php
/**
 * Emergency Template Debug Tool
 *
 * Speichere als: debug_template.php im Projekt-Root
 * Ausführen mit: php debug_template.php
 * Oder über Browser: http://yoursite.com/debug_template.php
 */

declare(strict_types=1);

// Fehler-Anzeige aktivieren
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

echo "<h1>🔍 KickersCup Template System Emergency Debug</h1>\n";

// Bootstrap Check
echo "<h2>📋 Bootstrap Check</h2>\n";

$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    die("❌ FATAL: Composer autoload not found at: $autoloadFile\n");
}

require_once $autoloadFile;
echo "✅ Autoloader loaded<br>\n";

// Basis-Klassen Check
echo "<h2>🔧 Core Classes Check</h2>\n";

$coreClasses = [
    'Framework\\Core\\ApplicationKernel',
    'Framework\\Core\\ServiceContainer',
    'Framework\\Templating\\TemplateEngine',
    'Framework\\Templating\\TemplateCache',
    'Framework\\Templating\\FilterManager',
    'Framework\\Cache\\CacheDriverInterface',
];

foreach ($coreClasses as $class) {
    if (class_exists($class) || interface_exists($class)) {
        echo "✅ $class<br>\n";
    } else {
        echo "❌ $class<br>\n";
    }
}

// Template System Test
echo "<h2>🎨 Template System Test</h2>\n";

try {
    // 1. ServiceContainer Test
    echo "<h3>1. ServiceContainer Test</h3>\n";
    $container = new \Framework\Core\ServiceContainer();
    echo "✅ ServiceContainer created<br>\n";

    // 2. ApplicationKernel Test
    echo "<h3>2. ApplicationKernel Test</h3>\n";
    $app = new \Framework\Core\ApplicationKernel(__DIR__);
    echo "✅ ApplicationKernel created<br>\n";

    // 3. Template Cache Test
    echo "<h3>3. Template Cache Test</h3>\n";
    $cacheDir = __DIR__ . '/storage/cache/views';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
        echo "✅ Cache directory created<br>\n";
    }

    $templateCache = \Framework\Templating\TemplateCache::create($cacheDir, true);
    echo "✅ TemplateCache created: " . get_class($templateCache) . "<br>\n";

    // 4. Filter Manager Test
    echo "<h3>4. Filter Manager Test</h3>\n";
    $filterManager = new \Framework\Templating\FilterManager();
    echo "✅ FilterManager created<br>\n";

    // Test basic filters
    $testValue = "Hello World";
    $upperResult = $filterManager->execute('upper', $testValue);
    echo "✅ Filter 'upper' test: '$testValue' → '$upperResult'<br>\n";

    // 5. Template Path Resolver Test
    echo "<h3>5. Template Path Resolver Test</h3>\n";
    $viewsDir = __DIR__ . '/app/Views';
    if (!is_dir($viewsDir)) {
        mkdir($viewsDir, 0755, true);
        echo "✅ Views directory created<br>\n";
    }

    $pathResolver = new \Framework\Templating\Parsing\TemplatePathResolver([$viewsDir]);
    echo "✅ TemplatePathResolver created<br>\n";

    // 6. Template Engine Test
    echo "<h3>6. Template Engine Test</h3>\n";
    $templateEngine = new \Framework\Templating\TemplateEngine(
        $pathResolver,
        $templateCache,
        $filterManager
    );
    echo "✅ TemplateEngine created<br>\n";

    // 7. Simple Template Test
    echo "<h3>7. Simple Template Test</h3>\n";

    // Create a simple test template
    $testTemplate = $viewsDir . '/test.html';
    $testContent = '<h1>Hello {{ name }}!</h1><p>Current time: {{ time }}</p>';
    file_put_contents($testTemplate, $testContent);
    echo "✅ Test template created<br>\n";

    $testData = [
        'name' => 'KickersCup Manager',
        'time' => date('Y-m-d H:i:s')
    ];

    try {
        $rendered = $templateEngine->render('test', $testData);
        echo "✅ Template rendered successfully:<br>\n";
        echo "<div style='border: 1px solid green; padding: 10px; margin: 10px 0; background: #f0f8ff;'>\n";
        echo htmlspecialchars($rendered);
        echo "</div>\n";
    } catch (\Throwable $e) {
        echo "❌ Template rendering failed:<br>\n";
        echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>\n";
        echo "<strong>File:</strong> " . htmlspecialchars($e->getFile()) . "<br>\n";
        echo "<strong>Line:</strong> " . $e->getLine() . "<br>\n";
        echo "<details><summary>Stack Trace</summary><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>\n";
    }

} catch (\Throwable $e) {
    echo "❌ Critical Error in Template System:<br>\n";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>\n";
    echo "<strong>File:</strong> " . htmlspecialchars($e->getFile()) . "<br>\n";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>\n";
    echo "<details><summary>Stack Trace</summary><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>\n";
}

// Service Provider Test
echo "<h2>🔌 Service Provider Test</h2>\n";

try {
    $container = new \Framework\Core\ServiceContainer();
    $app = new \Framework\Core\ApplicationKernel(__DIR__);

    $templatingProvider = new \Framework\Templating\TemplatingServiceProvider($container, $app);
    echo "✅ TemplatingServiceProvider created<br>\n";

    $templatingProvider->register();
    echo "✅ TemplatingServiceProvider registered<br>\n";

    // Test service resolution
    if ($container->has(\Framework\Templating\TemplateEngine::class)) {
        echo "✅ TemplateEngine service registered<br>\n";

        $engine = $container->get(\Framework\Templating\TemplateEngine::class);
        echo "✅ TemplateEngine resolved: " . get_class($engine) . "<br>\n";
    } else {
        echo "❌ TemplateEngine service not registered<br>\n";
    }

} catch (\Throwable $e) {
    echo "❌ Service Provider Error:<br>\n";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>\n";
    echo "<strong>File:</strong> " . htmlspecialchars($e->getFile()) . "<br>\n";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>\n";
    echo "<details><summary>Stack Trace</summary><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>\n";
}

// File System Check
echo "<h2>📁 File System Check</h2>\n";

$directories = [
    'app/Views' => __DIR__ . '/app/Views',
    'app/Config' => __DIR__ . '/app/Config',
    'storage/cache/views' => __DIR__ . '/storage/cache/views',
    'storage/logs' => __DIR__ . '/storage/logs',
];

foreach ($directories as $name => $path) {
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);

    if ($exists && $writable) {
        echo "✅ $name: OK<br>\n";
    } elseif ($exists) {
        echo "⚠️ $name: Read-only<br>\n";
    } else {
        echo "❌ $name: Missing<br>\n";

        // Try to create
        if (mkdir($path, 0755, true)) {
            echo "✅ $name: Created<br>\n";
        } else {
            echo "❌ $name: Failed to create<br>\n";
        }
    }
}

// Config Check
echo "<h2>⚙️ Configuration Check</h2>\n";

$configFile = __DIR__ . '/app/Config/templating.php';
if (file_exists($configFile)) {
    echo "✅ templating.php found<br>\n";

    try {
        $config = include $configFile;
        echo "✅ Configuration loaded<br>\n";
        echo "<pre>" . htmlspecialchars(print_r($config, true)) . "</pre>\n";
    } catch (\Throwable $e) {
        echo "❌ Configuration error: " . htmlspecialchars($e->getMessage()) . "<br>\n";
    }
} else {
    echo "❌ templating.php missing<br>\n";
    echo "Creating basic configuration...<br>\n";

    $basicConfig = "<?php\n\nreturn [\n    'paths' => ['app/Views'],\n    'cache' => [\n        'enabled' => false, // Temporarily disabled\n        'path' => 'storage/cache/views',\n    ],\n    'options' => [\n        'auto_escape' => true,\n        'debug' => true,\n    ],\n];\n";

    if (!is_dir(dirname($configFile))) {
        mkdir(dirname($configFile), 0755, true);
    }

    file_put_contents($configFile, $basicConfig);
    echo "✅ Basic configuration created<br>\n";
}

// Log Check
echo "<h2>📝 Log Check</h2>\n";

$logFile = __DIR__ . '/storage/logs/php_errors.log';
if (file_exists($logFile)) {
    echo "✅ Error log found<br>\n";

    $logContent = file_get_contents($logFile);
    $lastLines = array_slice(explode("\n", $logContent), -10);

    echo "<h4>Last 10 log entries:</h4>\n";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;'>";
    echo htmlspecialchars(implode("\n", $lastLines));
    echo "</pre>\n";
} else {
    echo "❌ No error log found<br>\n";
}

echo "<h2>✅ Debug Complete</h2>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>Check the error log entries above for specific issues</li>\n";
echo "<li>Ensure all directories are writable</li>\n";
echo "<li>Temporarily disable cache if issues persist</li>\n";
echo "<li>Check that all required classes exist</li>\n";
echo "</ul>\n";

// Emergency Quick Fix Suggestions
echo "<h2>🚨 Emergency Quick Fixes</h2>\n";
echo "<h3>1. Disable Template Cache (Temporary)</h3>\n";
echo "<pre>// In app/Config/templating.php\n'cache' => [\n    'enabled' => false, // ← Set to false\n]</pre>\n";

echo "<h3>2. Enable Debug Mode</h3>\n";
echo "<pre>// In app/Config/templating.php\n'options' => [\n    'debug' => true, // ← Set to true\n]</pre>\n";

echo "<h3>3. Clear All Caches</h3>\n";
echo "<pre>rm -rf storage/cache/views/*\nrm -rf storage/cache/routes.php</pre>\n";

echo "<h3>4. Check Permissions</h3>\n";
echo "<pre>chmod -R 755 storage/\nchmod -R 755 app/Views/</pre>\n";
?>