<?php
/**
 * Application Bootstrap
 * Entry point for the football manager application
 *
 * File: public/index.php
 * Directory: /public/
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Core\Application;
use Framework\Core\Container;
use Framework\Providers\ApplicationServiceProvider;
use Framework\Providers\CoreServiceProvider;
use Framework\Providers\DatabaseServiceProvider;
use Framework\Providers\EmailServiceProvider;
use Framework\Providers\RoutingServiceProvider;
use Framework\Providers\SecurityServiceProvider;
use Framework\Providers\SessionServiceProvider;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

// Error reporting for development
if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Set timezone
date_default_timezone_set('Europe/Berlin');

try {
    // Initialize optimized dependency injection container
    $container = new Container(
        enableCache: !filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)
    );

    // Register configuration first
    $container->bind('config', function () {
        return require __DIR__ . '/../config/app.php';
    });

    // Register service providers in dependency order
    $providers = [
        new CoreServiceProvider(),           // Templates, Logger (foundation)
        new SessionServiceProvider(),        // Session Management
        new SecurityServiceProvider(),       // CSRF, Password, Rate Limiting
        new DatabaseServiceProvider(),       // Database, Validator
        new EmailServiceProvider(),          // Email Services
        new RoutingServiceProvider(),        // Router
        new ApplicationServiceProvider(),    // App-specific Services (last)
    ];

    // Register all services
    foreach ($providers as $provider) {
        try {
            $provider->register($container);
        } catch (ReflectionException $e) {
            throw new RuntimeException(
                'Service registration failed for ' . get_class($provider) . ': ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    // Boot all services in order
    foreach ($providers as $provider) {
        try {
            $provider->boot($container);
        } catch (ReflectionException $e) {
            throw new RuntimeException(
                'Service boot failed for ' . get_class($provider) . ': ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    // Initialize and run application
    try {
        $app = new Application($container);
        $app->run();
    } catch (ReflectionException $e) {
        throw new RuntimeException('Application execution failed: ' . $e->getMessage(), 0, $e);
    }

} catch (ReflectionException $e) {
    // Handle reflection errors during dependency injection
    if (isset($container) && $container->has('logger')) {
        try {
            $container->get('logger')->error('Reflection error during bootstrap: ' . $e->getMessage(), [
                'exception' => $e,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        } catch (Throwable $logError) {
            error_log('Bootstrap reflection error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    } else {
        error_log('Bootstrap reflection error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }

    http_response_code(500);

    if (($_ENV['APP_DEBUG'] ?? false)) {
        echo '<pre>Reflection Error: ' . htmlspecialchars($e->getMessage()) . "\n\n";
        echo 'File: ' . htmlspecialchars($e->getFile()) . "\n";
        echo 'Line: ' . $e->getLine() . "\n\n";
        echo 'Stack trace:' . "\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo "Service configuration error. Please contact the administrator.";
    }
} catch (Throwable $e) {
    // Log error and show user-friendly message
    if (isset($container) && $container->has('logger')) {
        try {
            $container->get('logger')->error('Application error: ' . $e->getMessage(), [
                'exception' => $e,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        } catch (Throwable $logError) {
            error_log('Bootstrap error: ' . $e->getMessage());
        }
    } else {
        error_log('Bootstrap error: ' . $e->getMessage());
    }

    http_response_code(500);

    if (($_ENV['APP_DEBUG'] ?? false)) {
        echo '<pre>' . $e . '</pre>';
    } else {
        echo "An error occurred. Please try again later.";
    }
}