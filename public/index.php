<?php

declare(strict_types=1);

/**
 * KickersCup Manager - Application Entry Point
 *
 * UPDATED: Verwendet ApplicationKernel statt Application
 *
 * Diese Datei ist der einzige öffentlich zugängliche Entry Point der Anwendung.
 * Alle HTTP-Requests werden durch diese Datei verarbeitet.
 */

// ===================================================================
// Error Handling & Performance Setup
// ===================================================================

// Performance: Start Output Buffering
ob_start();

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

// ← GEÄNDERT: ApplicationKernel statt Application

try {
    // Initialize Application Kernel
    $app = new ApplicationKernel(__DIR__ . '/..');  // ← GEÄNDERT

    // Create Request from Global Variables
    $request = Request::fromGlobals();

    // Handle Request and Generate Response
    $response = $app->handleRequest($request);

    // Send Response to Browser
    $response->send();

} catch (Throwable $e) {
    // ===================================================================
    // Emergency Error Handling
    // ===================================================================

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
        echo "<!DOCTYPE html>
<html lang=de>
<head>
    <title>Application Error</title>
    <style>
        body { font-family: monospace; background: #f8f8f8; padding: 20px; }
        .error { background: #fff; border-left: 5px solid #e74c3c; padding: 20px; margin: 20px 0; }
        .trace { background: #ecf0f1; padding: 15px; margin: 10px 0; white-space: pre-wrap; }
        h1 { color: #e74c3c; }
    </style>
</head>
<body>
    <h1>Application Error</h1>
    <div class='error'>
        <strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br>
        <strong>File:</strong> " . htmlspecialchars($e->getFile()) . "<br>
        <strong>Line:</strong> " . $e->getLine() . "
    </div>
    <div class='trace'>" . htmlspecialchars($e->getTraceAsString()) . "</div>
</body>
</html>";
    } else {
        // Production: Show generic error
        echo "<!DOCTYPE html>
<html lang=de>
<head>
    <title>Service Unavailable</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f8f8f8; }
        .error-box { background: white; padding: 40px; border-radius: 5px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #e74c3c; margin-bottom: 20px; }
        p { color: #7f8c8d; line-height: 1.6; }
    </style>
</head>
<body>
    <div class='error-box'>
        <h1>Service Temporarily Unavailable</h1>
        <p>We're sorry, but the service is temporarily unavailable.<br>
        Please try again in a few minutes.</p>
        <p><small>Error Code: 500</small></p>
    </div>
</body>
</html>";
    }
}

// ===================================================================
// Optional: Performance Monitoring
// ===================================================================

if (function_exists('fastcgi_finish_request')) {
    // If using PHP-FPM, finish the request to the client
    // This allows any cleanup code to run without affecting response time
    fastcgi_finish_request();
}

// Optional: Log request metrics, cleanup, etc.
// This code runs after the response is sent to the client
if (isset($app) && $app->isDebug()) {
    $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    $memoryUsage = memory_get_peak_usage(true);

    error_log(sprintf(
        "Request Performance: %s %s - Time: %.3fs, Memory: %s",
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $_SERVER['REQUEST_URI'] ?? '/',
        $executionTime,
        number_format($memoryUsage / 1024 / 1024, 2) . 'MB'
    ));
}