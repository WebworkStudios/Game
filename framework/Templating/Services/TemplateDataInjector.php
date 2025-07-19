<?php

declare(strict_types=1);

namespace Framework\Templating\Services;

use Framework\Localization\Translator;
use Framework\Security\Csrf;

/**
 * TemplateDataInjector - SRP: Verantwortlich NUR für Datenaufbereitung
 *
 * Früher: Teil des ViewRenderer (SRP-Verletzung)
 * Jetzt: Eigene Klasse mit klarer Verantwortung
 */
readonly class TemplateDataInjector
{
    public function __construct(
        private ?Translator $translator = null,
        private ?Csrf $csrf = null,
        private array $appConfig = []
    ) {}

    /**
     * Injiziert alle Framework-Services in Template-Daten
     */
    public function injectFrameworkServices(array $data): array
    {
        $data = $this->injectGlobalVariables($data);
        $data = $this->injectTranslationServices($data);
        $data = $this->injectSecurityServices($data);

        return $data;
    }

    /**
     * Global Template Variables
     */
    private function injectGlobalVariables(array $data): array
    {
        $data['app_name'] = $this->appConfig['name'] ?? 'KickersCup Manager';
        $data['app_version'] = $this->appConfig['version'] ?? '2.0.0';
        $data['app_debug'] = $this->appConfig['debug'] ?? false;
        $data['app_locale'] = $this->appConfig['locale'] ?? 'de';

        // Asset-URLs
        $data['asset_url'] = '/assets';
        $data['js_url'] = '/js';
        $data['css_url'] = '/css';

        return $data;
    }

    /**
     * Translation Services
     */
    private function injectTranslationServices(array $data): array
    {
        if ($this->translator === null) {
            $data['current_locale'] = $this->appConfig['locale'] ?? 'de';
            $data['available_locales'] = ['de', 'en'];
            $data['trans'] = fn(string $key, array $params = []) => $key;
            return $data;
        }

        try {
            $data['current_locale'] = $this->translator->getLocale();
            $data['available_locales'] = $this->translator->getSupportedLocales();
            $data['trans'] = fn(string $key, array $params = []) =>
            $this->translator->translate($key, $params);
        } catch (\Throwable) {
            // Graceful fallback
            $data['current_locale'] = $this->appConfig['locale'] ?? 'de';
            $data['available_locales'] = ['de', 'en'];
            $data['trans'] = fn(string $key, array $params = []) => $key;
        }

        return $data;
    }

    /**
     * Security Services (CSRF Tokens)
     */
    private function injectSecurityServices(array $data): array
    {
        if ($this->csrf === null) {
            $data['csrf_token'] = '';
            $data['csrf_field'] = '';
            return $data;
        }

        try {
            $data['csrf_token'] = $this->csrf->getToken();
            $data['csrf_field'] = $this->csrf->getTokenField();
        } catch (\Throwable) {
            $data['csrf_token'] = '';
            $data['csrf_field'] = '';
        }

        return $data;
    }
}