<?php

declare(strict_types=1);

// Autoloader (Composer oder eigener)
require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Core\Application;

// Application erstellen
$app = new Application(__DIR__ . '/..');

// Debug-Modus (in Production: false)
$app->setDebug(true);

// Framework installieren (beim ersten Aufruf)
if (!file_exists(__DIR__ . '/../app/Config/database.php')) {
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
$app->setErrorHandler(function (Throwable $e) {
    // Hier könnte Logging, Monitoring, etc. stattfinden
    error_log("Application Error: " . $e->getMessage());

    // Return null für Standard-Behandlung
    return null;
});

// Custom 404 Handler
$app->setNotFoundHandler(function ($request) {
    if ($request->expectsJson() || $request->isAjax() || str_starts_with($request->getPath(), '/api/')) {
        return \Framework\Http\Response::json([
            'error' => 'Route not found',
            'path' => $request->getPath(),
            'method' => $request->getMethod()->value,
        ], \Framework\Http\HttpStatus::NOT_FOUND);
    }

    // HTML für normale Browser-Requests
    return \Framework\Http\Response::notFound("
        <!DOCTYPE html>
        <html lang='de'>
        <head>
            <title>404 - Page Not Found</title>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #333;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    max-width: 500px;
                    width: 90%;
                    text-align: center;
                }
                .code { 
                    font-size: 4em; 
                    font-weight: bold; 
                    color: #e74c3c; 
                    margin-bottom: 20px;
                    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
                }
                .title { 
                    font-size: 1.5em; 
                    margin-bottom: 15px; 
                    color: #333;
                    font-weight: 600;
                }
                .message {
                    color: #666;
                    margin-bottom: 25px;
                    line-height: 1.6;
                }
                .path {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 20px 0;
                    font-family: monospace;
                    color: #495057;
                    border: 1px solid #dee2e6;
                }
                .actions {
                    margin-top: 30px;
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .btn {
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    display: inline-block;
                }
                .btn-primary {
                    background: #007bff;
                    color: white;
                }
                .btn-primary:hover {
                    background: #0056b3;
                    transform: translateY(-2px);
                }
                .btn-secondary {
                    background: #6c757d;
                    color: white;
                }
                .btn-secondary:hover {
                    background: #545b62;
                    transform: translateY(-2px);
                }
                @media (max-width: 480px) {
                    .container { padding: 25px; }
                    .code { font-size: 3em; }
                    .actions { flex-direction: column; }
                    .btn { width: 100%; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='code'>404</div>
                <div class='title'>Page Not Found</div>
                <div class='message'>
                    Sorry, the page you are looking for doesn't exist.
                </div>
                <div class='path'>
                    <strong>Path:</strong> {$request->getPath()}<br>
                    <strong>Method:</strong> {$request->getMethod()->value}
                </div>
                <div class='actions'>
                    <a href='javascript:history.back()' class='btn btn-secondary'>← Go Back</a>
                    <a href='/' class='btn btn-primary'>Home</a>
                </div>
            </div>
        </body>
        </html>
    ");
});

// Anwendung starten
$app->run();