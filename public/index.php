<?php

declare(strict_types=1);

// Autoloader (Composer)
require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Core\Application;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;

// Error Reporting für Development
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    // Application erstellen
    $app = new Application(__DIR__ . '/..');

    // Debug-Modus (in Production: false)
    $app->setDebug(true);

    // Framework installieren (beim ersten Aufruf)
    if (!$app->isInstalled()) {
        echo "Installing framework...\n";
        if ($app->install()) {
            echo "Framework installed successfully!\n";
            echo "Please configure your database in app/Config/database.php\n";
        } else {
            echo "Installation failed!\n";
            exit(1);
        }
    }

    // Custom Error Handler (optional)
    $app->setErrorHandler(function (Throwable $e, Request $request) {
        // Hier könnte Logging, Monitoring, etc. stattfinden
        error_log("Application Error: " . $e->getMessage());

        // Return null für Standard-Behandlung
        return null;
    });

    // Custom 404 Handler
    $app->setNotFoundHandler(function (Request $request) {
        if ($request->expectsJson() || str_starts_with($request->getPath(), '/api/')) {
            return Response::json([
                'error' => 'Route not found',
                'path' => $request->getPath(),
                'method' => $request->getMethod()->value,
                'timestamp' => date('c'),
            ], HttpStatus::NOT_FOUND);
        }

        // HTML für normale Browser-Requests
        $html = sprintf('
            <!DOCTYPE html>
            <html lang="de">
            <head>
                <title>404 - Page Not Found</title>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <style>
                    * { 
                        margin: 0; 
                        padding: 0; 
                        box-sizing: border-box; 
                    }
                    body { 
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%);
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: #333;
                        padding: 20px;
                    }
                    .container {
                        background: white;
                        padding: 40px;
                        border-radius: 12px;
                        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                        text-align: center;
                        max-width: 500px;
                        width: 100%%;
                    }
                    h1 {
                        color: #e74c3c;
                        font-size: 4rem;
                        font-weight: 700;
                        margin-bottom: 20px;
                        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
                    }
                    h2 {
                        color: #2c3e50;
                        font-size: 1.5rem;
                        margin-bottom: 30px;
                        font-weight: 300;
                    }
                    .path {
                        background: #ecf0f1;
                        padding: 15px;
                        border-radius: 8px;
                        margin: 20px 0;
                        font-family: monospace;
                        color: #7f8c8d;
                        word-break: break-all;
                    }
                    .actions {
                        margin-top: 30px;
                    }
                    .btn {
                        display: inline-block;
                        padding: 12px 24px;
                        margin: 0 10px;
                        border-radius: 6px;
                        text-decoration: none;
                        font-weight: 500;
                        transition: all 0.3s ease;
                    }
                    .btn-primary {
                        background: #3498db;
                        color: white;
                    }
                    .btn-primary:hover {
                        background: #2980b9;
                        transform: translateY(-2px);
                    }
                    .btn-secondary {
                        background: #95a5a6;
                        color: white;
                    }
                    .btn-secondary:hover {
                        background: #7f8c8d;
                        transform: translateY(-2px);
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>404</h1>
                    <h2>Oops! Page not found</h2>
                    <p>The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
                    
                    <div class="path">
                        <strong>Path:</strong> %s<br>
                        <strong>Method:</strong> %s
                    </div>
                    
                    <div class="actions">
                        <a href="javascript:history.back()" class="btn btn-secondary">← Go Back</a>
                        <a href="/" class="btn btn-primary">🏠 Home</a>
                    </div>
                </div>
            </body>
            </html>',
            htmlspecialchars($request->getPath()),
            htmlspecialchars($request->getMethod()->value)
        );

        return Response::notFound($html);
    });

    // Anwendung starten - Kompatibel mit alter run() Signatur
    $request = Request::fromGlobals();
    $response = $app->run($request);
    $response->send();

} catch (Throwable $e) {
    // Fallback Error Handler (falls Application-Level Handler fehlschlägt)
    http_response_code(500);

    if (ini_get('display_errors')) {
        // Development: Zeige detaillierten Fehler
        echo sprintf('
            <!DOCTYPE html>
            <html>
            <head>
                <title>Fatal Error</title>
                <style>
                    body { 
                        font-family: monospace; 
                        padding: 20px; 
                        background: #f8f8f8; 
                        color: #333;
                    }
                    .error { 
                        background: #fff; 
                        padding: 20px; 
                        border-left: 5px solid #e74c3c;
                        margin-bottom: 20px;
                    }
                    .trace { 
                        background: #f0f0f0; 
                        padding: 15px; 
                        overflow: auto;
                        font-size: 12px;
                    }
                    pre { white-space: pre-wrap; }
                    h1 { color: #e74c3c; margin-bottom: 10px; }
                    .info { color: #7f8c8d; margin-bottom: 5px; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h1>Fatal Error: %s</h1>
                    <div class="info"><strong>File:</strong> %s</div>
                    <div class="info"><strong>Line:</strong> %d</div>
                    <div class="info"><strong>Message:</strong> %s</div>
                </div>
                <div class="trace">
                    <h3>Stack Trace:</h3>
                    <pre>%s</pre>
                </div>
            </body>
            </html>',
            htmlspecialchars(get_class($e)),
            htmlspecialchars($e->getFile()),
            $e->getLine(),
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getTraceAsString())
        );
    } else {
        // Production: Zeige generischen Fehler
        echo '
            <!DOCTYPE html>
            <html>
            <head>
                <title>Server Error</title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        text-align: center; 
                        padding: 50px;
                        background: #f8f9fa;
                        color: #343a40;
                    }
                    .error-container { 
                        max-width: 500px; 
                        margin: 0 auto;
                        background: white;
                        padding: 40px;
                        border-radius: 8px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                    h1 { color: #dc3545; margin-bottom: 20px; }
                    p { color: #6c757d; line-height: 1.6; }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <h1>500 - Internal Server Error</h1>
                    <p>Something went wrong on our end. Please try again later.</p>
                    <p>If the problem persists, please contact support.</p>
                </div>
            </body>
            </html>';
    }

    // Log error
    error_log("Fatal Application Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    exit(1);
}