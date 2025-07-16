<?php

declare(strict_types=1);

// Autoloader (Composer)
require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Core\Application;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Router;

// Error Reporting für Development
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    // Application erstellen
    $app = new Application(__DIR__ . '/..');

    // Debug-Modus (in Production: false)
    $app->setDebug(true);

    // Custom Error Handler (optional)
    $app->setErrorHandler(function (Throwable $e, Request $request) {
        // Hier könnte Logging, Monitoring, etc. stattfinden
        error_log("Application Error: " . $e->getMessage());

        // Return null für Standard-Behandlung
        return null;
    });

    // Custom 404 Handler - Direkt über Router setzen
    $router = $app->getContainer()->get(Router::class);
    $responseFactory = $app->getContainer()->get(ResponseFactory::class); // FIX: ResponseFactory holen

    $router->setNotFoundHandler(function (Request $request) use ($responseFactory) {
        if ($request->expectsJson() || str_starts_with($request->getPath(), '/api/')) {
            // FIX: Verwende ResponseFactory statt Response::json()
            return $responseFactory->json([
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
                        color: white;
                    }
                    .container {
                        text-align: center;
                        background: rgba(255, 255, 255, 0.1);
                        padding: 40px;
                        border-radius: 20px;
                        backdrop-filter: blur(10px);
                        border: 1px solid rgba(255, 255, 255, 0.2);
                        max-width: 600px;
                        width: 90%%;
                    }
                    h1 {
                        font-size: 4rem;
                        margin-bottom: 20px;
                        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
                    }
                    h2 {
                        font-size: 1.5rem;
                        margin-bottom: 30px;
                        opacity: 0.9;
                    }
                    p {
                        font-size: 1.1rem;
                        margin-bottom: 30px;
                        opacity: 0.8;
                        line-height: 1.6;
                    }
                    .path {
                        background: rgba(0, 0, 0, 0.2);
                        padding: 20px;
                        border-radius: 10px;
                        margin: 30px 0;
                        font-family: monospace;
                        text-align: left;
                    }
                    .path strong {
                        color: #ffd700;
                    }
                    .actions {
                        margin-top: 30px;
                    }
                    .btn {
                        display: inline-block;
                        padding: 12px 24px;
                        margin: 0 10px;
                        text-decoration: none;
                        border-radius: 25px;
                        font-weight: 600;
                        transition: all 0.3s ease;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                    }
                    .btn-primary {
                        background: linear-gradient(45deg, #ff6b6b, #ee5a24);
                        color: white;
                    }
                    .btn-secondary {
                        background: rgba(255, 255, 255, 0.2);
                        color: white;
                        border: 1px solid rgba(255, 255, 255, 0.3);
                    }
                    .btn:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
                    }
                    @media (max-width: 768px) {
                        h1 { font-size: 2.5rem; }
                        .container { padding: 20px; }
                        .btn { display: block; margin: 10px 0; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>404</h1>
                    <h2>Page not found</h2>
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

        // FIX: Verwende ResponseFactory statt Response::notFound()
        return $responseFactory->notFound($html);
    });

    // Anwendung starten - Kompatibel mit alter run() Signatur
    $request = Request::fromGlobals();
    $response = $app->handleRequest($request); // FIX: Verwende handleRequest() statt run()
    $response->send();

} catch (Throwable $e) {
    // Fallback Error Handler (falls Application-Level Handler fehlschlägt)
    http_response_code(500);

    if (ini_get('display_errors')) {
        // Development: Zeige detaillierten Fehler
        echo sprintf('
            <!DOCTYPE html>
            <html lang=de>
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
            get_class($e),
            $e->getFile(),
            $e->getLine(),
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getTraceAsString())
        );
    } else {
        // Production: Zeige generischen Fehler
        echo '<!DOCTYPE html>
        <html lang=de>
        <head>
            <title>Server Error</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    margin: 0;
                }
                .container {
                    text-align: center;
                    background: rgba(255, 255, 255, 0.1);
                    padding: 40px;
                    border-radius: 20px;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                }
                h1 { font-size: 3rem; margin-bottom: 20px; }
                p { font-size: 1.2rem; opacity: 0.9; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>500</h1>
                <p>Internal Server Error</p>
                <p>Something went wrong on our end. Please try again later.</p>
            </div>
        </body>
        </html>';
    }
}