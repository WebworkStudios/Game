<?php

declare(strict_types=1);

/**
 * KickersCup Manager - Application Entry Point
 *
 * Diese Datei ist der einzige öffentlich zugängliche Entry Point der Anwendung.
 * Alle HTTP-Requests werden durch diese Datei verarbeitet.
 */

// Load Composer Autoloader
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    http_response_code(500);
    die('Composer autoload not found. Please run: composer install');
}

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Core\ApplicationKernel;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\ResponseFactory;

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

    // Log error (essential for production debugging)
    error_log("Critical Application Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    // Send appropriate HTTP status
    http_response_code(500);

    try {
        if (isset($app)) {
            // Use ResponseFactory for consistent error handling
            $responseFactory = $app->get(ResponseFactory::class);

            if ($app->isDebug()) {
                // Development: Use error template with detailed info
                $errorResponse = $responseFactory->view('errors/debug', [
                    'title' => 'Application Error',
                    'template' => 'N/A (Application Error)',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'data_keys' => 'N/A',
                    'php_version' => PHP_VERSION,
                    'memory_usage' => formatBytes(memory_get_usage(true)),
                    'memory_peak' => formatBytes(memory_get_peak_usage(true))
                ], HttpStatus::INTERNAL_SERVER_ERROR);
            } else {
                // Production: Use generic error template
                $errorResponse = $responseFactory->view('errors/production', [
                    'title' => 'Service Unavailable',
                    'message' => 'We are currently experiencing technical difficulties. Please try again later.'
                ], HttpStatus::INTERNAL_SERVER_ERROR);
            }

            $errorResponse->send();
        }
    } catch (Throwable $fallbackError) {
        // Last resort fallback
        error_log("Error handler failed: " . $fallbackError->getMessage());
    }
}



/**
 * Helper function for memory formatting
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision) . ' ' . $units[$pow];
}
