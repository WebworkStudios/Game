<?php

/**
 * Email Service Provider
 * Email and communication services
 *
 * File: framework/Providers/EmailServiceProvider.php
 * Directory: /framework/Providers/
 */

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\ServiceProvider;
use Framework\Core\TemplateEngine;
use Framework\Email\EmailService;

class EmailServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Email Service
        $container->singleton(EmailService::class, function ($container) {
            $config = $container->get('config');
            $templateEngine = $container->get(TemplateEngine::class);

            return new EmailService($config['email'], $templateEngine);
        });

        // Alias for easier access
        $container->alias('email', EmailService::class);
    }

    public function boot(Container $container): void
    {
        $config = $container->get('config');

        // Test email configuration in development
        if ($config['app']['debug']) {
            $this->testEmailConfiguration($container);
        }

        // Log email configuration
        $this->logEmailConfig($container, $config);
    }

    /**
     * Test email configuration during boot
     */
    private function testEmailConfiguration(Container $container): void
    {
        try {
            $emailService = $container->get(EmailService::class);
            $testResult = $emailService->testConfiguration();

            if (!empty($testResult['errors'])) {
                $container->get('logger')->warning('Email configuration issues detected', [
                    'errors' => $testResult['errors'],
                    'driver' => $testResult['driver']
                ]);
            } else {
                $container->get('logger')->debug('Email configuration validated', [
                    'driver' => $testResult['driver'],
                    'from_address' => $testResult['from_address']
                ]);
            }
        } catch (\Throwable $e) {
            $container->get('logger')->warning('Email service boot failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Log email configuration details
     */
    private function logEmailConfig(Container $container, array $config): void
    {
        $emailConfig = $config['email'];

        $logData = [
            'driver' => $emailConfig['driver'] ?? 'smtp',
            'from_address' => $emailConfig['from']['address'] ?? 'not_set',
            'from_name' => $emailConfig['from']['name'] ?? 'not_set',
            'queue_enabled' => $emailConfig['queue']['enabled'] ?? false,
        ];

        // Add SMTP details without credentials
        if (($emailConfig['driver'] ?? 'smtp') === 'smtp') {
            $smtpConfig = $emailConfig['smtp'] ?? [];
            $logData['smtp'] = [
                'host' => $smtpConfig['host'] ?? 'not_set',
                'port' => $smtpConfig['port'] ?? 'not_set',
                'encryption' => $smtpConfig['encryption'] ?? 'none',
                'auth_configured' => !empty($smtpConfig['username']),
            ];
        }

        $container->get('logger')->debug('Email service initialized', $logData);
    }
}