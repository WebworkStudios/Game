<?php
declare(strict_types=1);

namespace Framework\Mail;

use Framework\Core\AbstractServiceProvider;
use Framework\Core\ConfigValidation;
use Framework\Database\ConnectionManager;
use RuntimeException;

/**
 * Mail Service Provider - Registriert Mail-Services im Framework
 */
class MailServiceProvider extends AbstractServiceProvider
{
    use ConfigValidation;

    /**
     * Validiert Mail-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // Config-Validierung
        $this->ensureConfigExists('mail');

        // PHP Extensions prüfen
        if (!function_exists('stream_socket_client')) {
            throw new RuntimeException('Socket functions are required for SMTP functionality');
        }

        if (!function_exists('base64_encode')) {
            throw new RuntimeException('Base64 functions are required for SMTP authentication');
        }
    }

    /**
     * Registriert alle Mail Services
     */
    protected function registerServices(): void
    {
        $this->registerMailService();
        $this->registerMailTemplateService();
        $this->registerMailQueueProcessor();
    }

    /**
     * Registriert Haupt-MailService
     */
    private function registerMailService(): void
    {
        $this->singleton(MailService::class, function () {
            $config = $this->loadAndValidateConfig('mail');

            return new MailService(
                connectionManager: $this->get(ConnectionManager::class),
                config: $config
            );
        });
    }

    /**
     * Registriert Mail-Template-Service
     */
    private function registerMailTemplateService(): void
    {
        $this->singleton(MailTemplateService::class, function () {
            return new MailTemplateService(
                $this->get(ConnectionManager::class),
                $this->get(MailService::class)
            );
        });
    }

    /**
     * Registriert Mail-Queue-Processor (für Cron-Jobs)
     */
    private function registerMailQueueProcessor(): void
    {
        $this->singleton(MailQueueProcessor::class, function () {
            return new MailQueueProcessor(
                $this->get(MailService::class)
            );
        });
    }

    /**
     * Bindet Mail-Interfaces
     */
    protected function bindInterfaces(): void
    {
        // Hier können Mail-Interfaces gebunden werden
        // $this->bind(MailServiceInterface::class, MailService::class);
    }
}