<?php

/**
 * Core Service Provider
 * Basic framework services - Templates and Logging
 *
 * File: framework/Providers/CoreServiceProvider.php
 * Directory: /framework/Providers/
 */

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\Logger;
use Framework\Core\ServiceProvider;
use Framework\Core\TemplateEngine;

class CoreServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Template Engine
        $container->singleton(TemplateEngine::class, function ($container) {
            $config = $container->get('config');
            return new TemplateEngine($config['templates']['path'] ?? __DIR__ . '/../../templates/');
        });

        // Alias for easier access
        $container->alias('templates', TemplateEngine::class);

        // Logger
        $container->singleton('logger', function ($container) {
            $config = $container->get('config');
            return new Logger($config['logging']);
        });
    }

    public function boot(Container $container): void
    {
        // Initialize template globals
        $templateEngine = $container->get(TemplateEngine::class);
        $config = $container->get('config');

        // Add global template variables
        $this->addTemplateGlobals($templateEngine, $config);

        // Set up logging correlation ID
        $this->initializeLogging($container);
    }

    /**
     * Add global variables to templates
     */
    private function addTemplateGlobals(TemplateEngine $templateEngine, array $config): void
    {
        $globals = [
            'app_name' => $config['app']['name'] ?? 'Football Manager',
            'app_version' => $config['app']['version'] ?? '1.0.0',
            'app_env' => $config['app']['environment'] ?? 'production',
            'app_debug' => $config['app']['debug'] ?? false,
            'app_url' => $config['app']['url'] ?? '',
            'cdn_url' => $config['app']['cdn_url'] ?? null,
        ];

        foreach ($globals as $key => $value) {
            $templateEngine->addGlobal($key, $value);
        }
    }

    /**
     * Initialize logging system
     */
    private function initializeLogging(Container $container): void
    {
        $logger = $container->get('logger');

        // Set correlation ID from request header if available
        if (isset($_SERVER['HTTP_X_CORRELATION_ID'])) {
            $logger->setCorrelationId($_SERVER['HTTP_X_CORRELATION_ID']);
        }

        $logger->debug('Core services initialized', [
            'template_engine' => 'ready',
            'logger' => 'ready'
        ]);
    }
}