<?php

declare(strict_types=1);

/**
 * KickersCup Manager - Application Entry Point
 *
 * FIXED: Vollständiger Error-Handler mit korrekter HTML-Ausgabe
 *
 * Diese Datei ist der einzige öffentlich zugängliche Entry Point der Anwendung.
 * Alle HTTP-Requests werden durch diese Datei verarbeitet.
 */

// ===================================================================
// Bootstrap & Autoloading
// ===================================================================

// Load Composer Autoloader
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    http_response_code(500);
    die('Composer autoload not found. Please run: composer install');
}

require_once __DIR__ . '/../vendor/autoload.php';

// ===================================================================
// Framework Bootstrap
// ===================================================================

use Framework\Core\ApplicationKernel;
use Framework\Http\Request;

try {
    // Initialize Application Kernel
    $app = new ApplicationKernel(__DIR__ . '/..');

    // Create Request from Global Variables
    $request = Request::fromGlobals();

    // Handle Request and Generate Response
    $response = $app->handleRequest($request);

    // Send Response to Browser
    $response->send();

} catch (Throwable $e) {

    // Clear any output buffer to prevent mixed content
    if (ob_get_level()) {
        ob_clean();
    }

    // Log error (if possible)
    error_log("Critical Application Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    // Send appropriate HTTP status
    http_response_code(500);

    // Show error based on environment
    if (isset($app) && $app->isDebug()) {
        // Development: Show detailed error
        renderDebugErrorPage($e);
    } else {
        // Production: Show generic error
        renderProductionErrorPage();
    }
}

/**
 * GEFIXT: Vollständige Debug-Error-Page
 */
function renderDebugErrorPage(Throwable $e): void
{
    $title = htmlspecialchars($e->getMessage());
    $file = htmlspecialchars($e->getFile());
    $line = $e->getLine();
    $trace = htmlspecialchars($e->getTraceAsString());
    $phpVersion = PHP_VERSION;
    $memoryUsage = formatBytes(memory_get_usage(true));
    $memoryPeak = formatBytes(memory_get_peak_usage(true));

    echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Error - KickersCup Manager</title>
    <style>
        body { 
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Code', monospace; 
            margin: 0; 
            background: #1a1a1a; 
            color: #e1e1e1; 
            line-height: 1.6;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px; 
        }
        .header { 
            background: linear-gradient(135deg, #dc3545, #c82333); 
            padding: 30px; 
            border-radius: 8px; 
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .header h1 { 
            margin: 0 0 10px 0; 
            color: white; 
            font-size: 24px; 
        }
        .header p { 
            margin: 5px 0; 
            color: #f8d7da; 
        }
        .section { 
            background: #2d2d2d; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .section h3 { 
            margin-top: 0; 
            color: #ffc107; 
            font-size: 18px;
        }
        .info-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
            margin-bottom: 20px;
        }
        .trace { 
            background: #1e1e1e; 
            padding: 20px; 
            border-radius: 4px; 
            white-space: pre-wrap; 
            font-size: 13px;
            overflow-x: auto;
            border: 1px solid #444;
        }
        .highlight { 
            background: #495057; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-family: inherit;
        }
        .badge { 
            background: #17a2b8; 
            color: white; 
            padding: 4px 8px; 
            border-radius: 4px; 
            font-size: 12px; 
        }
        @media (max-width: 768px) { 
            .info-grid { grid-template-columns: 1fr; }
            .container { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Application Error</h1>
            <p><strong>Message:</strong> {$title}</p>
            <p><strong>Environment:</strong> <span class="badge">Development</span></p>
        </div>

        <div class="info-grid">
            <div class="section">
                <h3>📍 Error Location</h3>
                <p><strong>File:</strong> <span class="highlight">{$file}</span></p>
                <p><strong>Line:</strong> <span class="highlight">{$line}</span></p>
            </div>

            <div class="section">
                <h3>📊 System Information</h3>
                <p><strong>PHP Version:</strong> {$phpVersion}</p>
                <p><strong>Memory Usage:</strong> {$memoryUsage}</p>
                <p><strong>Memory Peak:</strong> {$memoryPeak}</p>
            </div>
        </div>

        <div class="section">
            <h3>🔍 Stack Trace</h3>
            <div class="trace">{$trace}</div>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * GEFIXT: Vollständige Production-Error-Page
 */
function renderProductionErrorPage(): void
{
    echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Unavailable - KickersCup Manager</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container { 
            background: white; 
            padding: 50px; 
            border-radius: 12px; 
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 500px;
            margin: 20px;
        }
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: #e74c3c;
        }
        h1 { 
            color: #2c3e50; 
            margin-bottom: 15px;
            font-size: 28px;
            font-weight: 300;
        }
        p { 
            color: #7f8c8d; 
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .error-code {
            background: #ecf0f1;
            padding: 8px 16px;
            border-radius: 20px;
            color: #95a5a6;
            font-size: 12px;
            display: inline-block;
            margin-top: 20px;
        }
        .retry-button {
            background: #3498db;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .retry-button:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>Service Temporarily Unavailable</h1>
        <p>We're sorry, but our service is temporarily unavailable due to maintenance or high traffic.</p>
        <p>Please try again in a few minutes.</p>
        <a href="javascript:location.reload()" class="retry-button">Try Again</a>
        <div class="error-code">Error Code: 500</div>
    </div>
</body>
</html>
HTML;
}

/**
 * Helper: Bytes in lesbare Form formatieren
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor(log($bytes, 1024));
    return sprintf('%.2f %s', $bytes / (1024 ** $factor), $units[$factor] ?? 'TB');
}

// ===================================================================
// Optional: Performance Monitoring
// ===================================================================

if (function_exists('fastcgi_finish_request')) {
    // If using PHP-FPM, finish the request to the client
    // This allows any cleanup code to run without affecting response time
    fastcgi_finish_request();
}