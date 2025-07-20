<?php

/**
 * FilterManager & Template Rendering Debug Tool
 *
 * Speichere als: debug_filter.php im Root
 * Ausführen: php debug_filter.php
 */

declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

echo "🔍 FilterManager & Template Rendering Deep Debug\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    // 1. FilterManager Deep Test
    echo "📋 FilterManager Deep Analysis:\n";

    $filterManager = new \Framework\Templating\FilterManager();
    echo "✅ FilterManager created\n";

    // Test alle verfügbaren Filter
    $availableFilters = $filterManager->getAvailableFilters();
    echo "📊 Available filters: " . count($availableFilters) . "\n";
    echo "📝 Filter list: " . implode(', ', $availableFilters) . "\n\n";

    // Test kritische Filter einzeln
    $testFilters = [
        'upper' => 'hello',
        'lower' => 'WORLD',
        'escape' => '<script>alert("test")</script>',
        'length' => [1, 2, 3],
        'number_format' => 1234.567,
        'date' => time(),
        'raw' => '<b>test</b>',
        'default' => null,
    ];

    echo "🧪 Testing individual filters:\n";
    foreach ($testFilters as $filterName => $testValue) {
        try {
            if ($filterManager->has($filterName)) {
                $result = $filterManager->execute($filterName, $testValue);
                $resultStr = is_array($result) ? '[Array]' : (string)$result;
                echo "✅ {$filterName}: " . var_export($testValue, true) . " → {$resultStr}\n";
            } else {
                echo "❌ {$filterName}: NOT AVAILABLE\n";
            }
        } catch (\Throwable $e) {
            echo "💥 {$filterName}: ERROR - " . $e->getMessage() . "\n";
        }
    }

    echo "\n";

    // 2. FilterRegistry Deep Dive
    echo "🔧 FilterRegistry Analysis:\n";

    $registry = new \Framework\Templating\Filters\FilterRegistry();
    echo "✅ FilterRegistry created\n";

    // Test Filter Registration
    try {
        $registry->register('test_filter', function ($value) {
            return strtoupper($value);
        });
        echo "✅ Custom filter registration works\n";

        $testCallable = $registry->get('test_filter');
        $result = $testCallable('hello world');
        echo "✅ Custom filter execution: 'hello world' → '{$result}'\n";
    } catch (\Throwable $e) {
        echo "❌ Filter registration failed: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // 3. Template with Filters Test
    echo "🎨 Template with Filters Test:\n";

    $templateCache = \Framework\Templating\TemplateCache::createDisabled();
    $pathResolver = new \Framework\Templating\Parsing\TemplatePathResolver([__DIR__ . '/app/Views']);
    $templateEngine = new \Framework\Templating\TemplateEngine($pathResolver, $templateCache, $filterManager);

    // Create test template with various filters
    $testTemplateContent = '
<h1>Filter Test Template</h1>
<p>Upper: {{ name|upper }}</p>
<p>Lower: {{ name|lower }}</p>
<p>Length: {{ items|length }}</p>
<p>Escape: {{ script|escape }}</p>
<p>Number: {{ price|number_format:2 }}</p>
<p>Default: {{ missing|default:"N/A" }}</p>
<p>Raw HTML: {{ html|raw }}</p>
    ';

    $testTemplatePath = __DIR__ . '/app/Views/filter_test.html';
    if (!is_dir(dirname($testTemplatePath))) {
        mkdir(dirname($testTemplatePath), 0755, true);
    }
    file_put_contents($testTemplatePath, trim($testTemplateContent));

    $testData = [
        'name' => 'KickersCup Manager',
        'items' => ['a', 'b', 'c'],
        'script' => '<script>alert("xss")</script>',
        'price' => 1234.567,
        'html' => '<strong>Bold Text</strong>',
    ];

    try {
        $rendered = $templateEngine->render('filter_test', $testData);
        echo "✅ Template with filters rendered successfully!\n";
        echo "📄 Rendered output:\n";
        echo str_repeat("-", 50) . "\n";
        echo $rendered . "\n";
        echo str_repeat("-", 50) . "\n\n";
    } catch (\Throwable $e) {
        echo "💥 Template rendering with filters FAILED:\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "Trace:\n" . $e->getTraceAsString() . "\n\n";
    }

    // 4. Production Scenario Test
    echo "🏭 Production Scenario Test:\n";

    try {
        // Simulate ApplicationKernel setup
        $container = new \Framework\Core\ServiceContainer();
        $app = new \Framework\Core\ApplicationKernel(__DIR__);

        // Register TemplatingServiceProvider
        $provider = new \Framework\Templating\TemplatingServiceProvider($container, $app);
        $provider->register();

        echo "✅ Production services registered\n";

        // Get ViewRenderer (wie in Production verwendet)
        $viewRenderer = $container->get(\Framework\Templating\ViewRenderer::class);
        echo "✅ ViewRenderer obtained\n";

        // Test ViewRenderer mit Filter-Template
        $response = $viewRenderer->render('filter_test', $testData);
        echo "✅ ViewRenderer->render() successful\n";
        echo "📊 Response status: " . $response->getStatus()->value . "\n";
        echo "📄 Response body length: " . strlen($response->getBody()) . " chars\n";

        // Check for filter execution in response
        $body = $response->getBody();
        if (strpos($body, 'KICKERSCUP MANAGER') !== false) {
            echo "✅ Upper filter worked in ViewRenderer\n";
        } else {
            echo "❌ Upper filter NOT working in ViewRenderer\n";
        }

        if (strpos($body, '&lt;script&gt;') !== false) {
            echo "✅ Escape filter worked in ViewRenderer\n";
        } else {
            echo "❌ Escape filter NOT working in ViewRenderer\n";
        }

    } catch (\Throwable $e) {
        echo "💥 Production scenario FAILED:\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "Trace:\n" . $e->getTraceAsString() . "\n\n";
    }

    // 5. Cache File Analysis
    echo "💾 Cache Analysis:\n";

    $cacheDir = __DIR__ . '/storage/cache/views';
    if (is_dir($cacheDir)) {
        $cacheFiles = glob($cacheDir . '/*');
        echo "📁 Cache files found: " . count($cacheFiles) . "\n";

        foreach ($cacheFiles as $file) {
            $size = filesize($file);
            $mtime = date('Y-m-d H:i:s', filemtime($file));
            echo "📄 " . basename($file) . " ({$size} bytes, modified: {$mtime})\n";

            // Analyze cache content
            if (is_readable($file)) {
                $content = file_get_contents($file);
                if (strpos($content, 'filter') !== false) {
                    echo "   Contains filter references ✅\n";
                } else {
                    echo "   No filter references ❌\n";
                }
            }
        }
    } else {
        echo "❌ Cache directory not found\n";
    }

    echo "\n";

    // 6. Error Log Check
    echo "📝 Error Log Analysis:\n";

    $logFile = __DIR__ . '/storage/logs/php_errors.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $lines = explode("\n", $logContent);
        $recentLines = array_slice($lines, -20); // Last 20 lines

        echo "📊 Total log lines: " . count($lines) . "\n";
        echo "📄 Recent entries (last 20):\n";
        foreach ($recentLines as $line) {
            if (trim($line)) {
                echo "  " . trim($line) . "\n";
            }
        }
    } else {
        echo "❌ No error log found\n";
    }

} catch (\Throwable $e) {
    echo "💥 CRITICAL ERROR in debug script:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🏁 Debug completed!\n";
