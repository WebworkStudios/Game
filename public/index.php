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
    if ($request->expectsJson()) {
        return \Framework\Http\Response::json([
            'error' => 'Route not found',
            'path' => $request->getPath(),
            'method' => $request->getMethod()->value,
        ], \Framework\Http\HttpStatus::NOT_FOUND);
    }

    return \Framework\Http\Response::notFound("
        <!DOCTYPE html>
        <html>
        <head>
            <title>404 - Page Not Found</title>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
                .error { color: #666; max-width: 600px; margin: 0 auto; }
                .code { font-size: 4em; font-weight: bold; color: #333; }
                .message { font-size: 1.2em; margin: 20px 0; }
                .path { background: #f5f5f5; padding: 10px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='error'>
                <div class='code'>404</div>
                <div class='message'>Page Not Found</div>
                <div class='path'>Path: {$request->getPath()}</div>
                <a href='/'>← Back to Home</a>
            </div>
        </body>
        </html>
    ");
});

// Anwendung starten
$app->run();