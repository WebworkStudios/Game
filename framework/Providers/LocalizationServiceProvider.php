<?php
declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\ServiceProvider;
use Framework\Core\SessionManagerInterface;
use Framework\Core\TemplateEngine;
use Framework\Localization\LocalizationService;

class LocalizationServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(LocalizationService::class, function ($container) {
            $config = $container->get('config');
            return new LocalizationService(
                $container->get('db'),
                $container->get('logger'),
                $config['localization'],
                $container->get(SessionManagerInterface::class) // Add session dependency
            );
        });

        $container->alias('localization', LocalizationService::class);
    }

    public function boot(Container $container): void
    {
        $localization = $container->get(LocalizationService::class);
        $templateEngine = $container->get(TemplateEngine::class);

        // Integrate with template engine
        $templateEngine->setLocalizationService($localization);

        // Preload critical translations in production
        $config = $container->get('config');
        if (!$config['app']['debug']) {
            $localization->preload(['general', 'validation', 'auth']);
        }

        $container->get('logger')->debug('Localization service initialized', [
            'current_locale' => $localization->currentLocale,
            'supported_locales' => $localization->supportedLocales,
            'fallback_locale' => $localization->fallbackLocale
        ]);
    }
}