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
use Framework\Core\Router;
use Framework\Providers\ApplicationServiceProvider;
use Framework\Providers\DatabaseServiceProvider;
use Framework\Providers\FrameworkServiceProvider;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
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

// Start session with secure settings
session_start([
    'cookie_lifetime' => 0,
    'cookie_path' => '/',
    'cookie_domain' => $_ENV['SESSION_DOMAIN'] ?? '',
    'cookie_secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

try {
    // Initialize dependency injection container
    $container = new Container();

    // Register configuration
    $container->bind('config', function () {
        return require __DIR__ . '/../config/app.php';
    });

    // Register service providers
    $providers = [
        new FrameworkServiceProvider(),
        new DatabaseServiceProvider(),
        new ApplicationServiceProvider(),
    ];

    // Register all services
    foreach ($providers as $provider) {
        $provider->register($container);
    }

    // Register router after all services
    $container->bind('router', function ($container) {
        return new Router($container);
    });

    // Boot all services
    foreach ($providers as $provider) {
        $provider->boot($container);
    }

    // Initialize and run application
    $app = new Application($container);
    $app->run();

} catch (Throwable $e) {
    // Log error and show user-friendly message
    if (isset($container) && $container->has('logger')) {
        $container->get('logger')->error('Application error: ' . $e->getMessage(), [
            'exception' => $e,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
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