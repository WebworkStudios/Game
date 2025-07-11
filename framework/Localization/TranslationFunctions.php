<?php


declare(strict_types=1);

namespace Framework\Localization;

use Framework\Core\ServiceRegistry;

/**
 * Global Translation Functions for Templates
 */
class TranslationFunctions
{
    /**
     * Add translation functions to template engine globals
     */
    public static function register(\Framework\Templating\TemplateEngine $engine): void
    {
        // Add translation helper functions
        $engine->addGlobal('t', function (string $key, array $parameters = []): string {
            return self::translate($key, $parameters);
        });

        $engine->addGlobal('t_plural', function (string $key, int $count, array $parameters = []): string {
            return self::translatePlural($key, $count, $parameters);
        });

        $engine->addGlobal('locale', function (): string {
            return self::getCurrentLocale();
        });

        $engine->addGlobal('locales', function (): array {
            return self::getSupportedLocales();
        });
    }

    /**
     * Translate function for templates
     */
    public static function translate(string $key, array $parameters = []): string
    {
        $translator = self::getTranslator();

        if (!$translator) {
            return $key; // Fallback
        }

        return $translator->translate($key, $parameters);
    }

    /**
     * Get translator instance
     */
    private static function getTranslator(): ?Translator
    {
        try {
            return ServiceRegistry::get(Translator::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Translate plural function for templates
     */
    public static function translatePlural(string $key, int $count, array $parameters = []): string
    {
        $translator = self::getTranslator();

        if (!$translator) {
            return $key; // Fallback
        }

        return $translator->translatePlural($key, $count, $parameters);
    }

    /**
     * Get current locale
     */
    public static function getCurrentLocale(): string
    {
        $translator = self::getTranslator();
        return $translator?->getLocale() ?? 'de';
    }

    /**
     * Get supported locales
     */
    public static function getSupportedLocales(): array
    {
        $translator = self::getTranslator();
        return $translator?->getSupportedLocales() ?? ['de', 'en', 'fr', 'es'];
    }
}