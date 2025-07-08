<?php


declare(strict_types=1);

// Autoloader (Composer oder eigener)
require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Core\Application;

// Application erstellen
$app = new Application(__DIR__ . '/..');

// Debug-Modus (in Production: false)
$app->setDebug(true);

// Custom Error Handler (optional)
$app->setErrorHandler(function (Throwable $e) {
    // Hier könnte Logging, Monitoring, etc. stattfinden
    error_log("Custom Error: " . $e->getMessage());

    // Return null für Standard-Behandlung
    return null;
});

// Custom 404 Handler (optional)
$app->setNotFoundHandler(function ($request) {
    return \Framework\Http\Response::json([
        'error' => 'Route not found',
        'path' => $request->getPath(),
        'method' => $request->getMethod()->value,
    ], \Framework\Http\HttpStatus::NOT_FOUND);
});

// Zusätzliche Services registrieren (optional)
//$app->singleton(\App\Services\UserService::class);

// Interface Bindings (optional)
// $app->bind(\App\Repositories\UserRepositoryInterface::class, \App\Repositories\UserRepository::class);

// Anwendung starten
try {
    $app->run();
} catch (ReflectionException $e) {

}