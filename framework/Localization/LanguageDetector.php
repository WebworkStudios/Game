<?php


declare(strict_types=1);

namespace Framework\Localization;

use Framework\Http\Request;
use Framework\Security\Session;

/**
 * Language Detection Service
 */
class LanguageDetector
{
    private const string SESSION_KEY = 'locale';
    private const string COOKIE_NAME = 'app_locale';
    private const int COOKIE_LIFETIME = 60 * 60 * 24 * 365; // 1 year

    public function __construct(
        private readonly Session $session,
        private readonly array   $supportedLocales = ['de', 'en', 'fr', 'es'],
        private readonly string  $defaultLocale = 'de'
    )
    {
    }

    /**
     * Detect language from request with priority order:
     * 1. Explicit URL parameter (?lang=en)
     * 2. User session preference
     * 3. Cookie preference
     * 4. Accept-Language header
     * 5. Default locale
     */
    public function detectLocale(Request $request): string
    {
        // 1. URL Parameter (highest priority)
        $urlLocale = $this->getLocaleFromUrl($request);
        if ($urlLocale !== null) {
            return $urlLocale;
        }

        // 2. Session preference
        $sessionLocale = $this->getLocaleFromSession();
        if ($sessionLocale !== null) {
            return $sessionLocale;
        }

        // 3. Cookie preference
        $cookieLocale = $this->getLocaleFromCookie($request);
        if ($cookieLocale !== null) {
            return $cookieLocale;
        }

        // 4. Accept-Language header
        $headerLocale = $this->getLocaleFromHeader($request);
        if ($headerLocale !== null) {
            return $headerLocale;
        }

        // 5. Default fallback
        return $this->defaultLocale;
    }

    /**
     * Set user's locale preference (stores in session and cookie)
     */
    public function setUserLocale(string $locale): void
    {
        if (!$this->isValidLocale($locale)) {
            throw new \InvalidArgumentException("Unsupported locale: {$locale}");
        }

        // Store in session
        $this->session->set(self::SESSION_KEY, $locale);

        // Store in cookie
        setcookie(
            self::COOKIE_NAME,
            $locale,
            time() + self::COOKIE_LIFETIME,
            '/',
            '',
            false, // Secure only in production
            true   // HTTP only
        );
    }

    /**
     * Get user's preferred locale from session
     */
    public function getUserLocale(): ?string
    {
        return $this->getLocaleFromSession();
    }

    /**
     * Clear user's locale preference
     */
    public function clearUserLocale(): void
    {
        $this->session->remove(self::SESSION_KEY);

        // Clear cookie
        setcookie(
            self::COOKIE_NAME,
            '',
            time() - 3600,
            '/'
        );
    }

    /**
     * Get best match from Accept-Language header
     */
    public function getBestMatchFromHeader(string $acceptLanguage): ?string
    {
        $languages = $this->parseAcceptLanguage($acceptLanguage);

        foreach ($languages as $language) {
            $locale = $this->normalizeLocale($language['locale']);
            if ($this->isValidLocale($locale)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Get all supported locales
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Check if locale is supported
     */
    public function isValidLocale(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales, true);
    }

    /**
     * Get locale from URL parameter
     */
    private function getLocaleFromUrl(Request $request): ?string
    {
        $locale = $request->input('lang');

        if ($locale && $this->isValidLocale($locale)) {
            return $locale;
        }

        return null;
    }

    /**
     * Get locale from session
     */
    private function getLocaleFromSession(): ?string
    {
        $locale = $this->session->get(self::SESSION_KEY);

        if ($locale && $this->isValidLocale($locale)) {
            return $locale;
        }

        return null;
    }

    /**
     * Get locale from cookie
     */
    private function getLocaleFromCookie(Request $request): ?string
    {
        $cookies = $request->getCookies();
        $locale = $cookies[self::COOKIE_NAME] ?? null;

        if ($locale && $this->isValidLocale($locale)) {
            return $locale;
        }

        return null;
    }

    /**
     * Get locale from Accept-Language header
     */
    private function getLocaleFromHeader(Request $request): ?string
    {
        $acceptLanguage = $request->getHeader('accept-language');

        if (!$acceptLanguage) {
            return null;
        }

        return $this->getBestMatchFromHeader($acceptLanguage);
    }

    /**
     * Parse Accept-Language header
     */
    private function parseAcceptLanguage(string $acceptLanguage): array
    {
        $languages = [];
        $parts = explode(',', $acceptLanguage);

        foreach ($parts as $part) {
            $part = trim($part);

            if (str_contains($part, ';q=')) {
                [$locale, $q] = explode(';q=', $part, 2);
                $quality = (float)$q;
            } else {
                $locale = $part;
                $quality = 1.0;
            }

            $languages[] = [
                'locale' => trim($locale),
                'quality' => $quality
            ];
        }

        // Sort by quality (highest first)
        usort($languages, fn($a, $b) => $b['quality'] <=> $a['quality']);

        return $languages;
    }

    /**
     * Normalize locale (en-US -> en, de-DE -> de)
     */
    private function normalizeLocale(string $locale): string
    {
        // Extract primary language code
        $parts = explode('-', $locale);
        $primary = strtolower($parts[0]);

        // Map common variations
        return match ($primary) {
            'en' => 'en',
            'de' => 'de',
            'fr' => 'fr',
            'es' => 'es',
            default => $primary
        };
    }

    /**
     * Get detection statistics for debugging
     */
    public function getDetectionInfo(Request $request): array
    {
        return [
            'url_param' => $this->getLocaleFromUrl($request),
            'session' => $this->getLocaleFromSession(),
            'cookie' => $this->getLocaleFromCookie($request),
            'accept_header' => $this->getLocaleFromHeader($request),
            'raw_accept_header' => $request->getHeader('accept-language'),
            'parsed_languages' => $request->getHeader('accept-language')
                ? $this->parseAcceptLanguage($request->getHeader('accept-language'))
                : null,
            'detected_locale' => $this->detectLocale($request),
            'supported_locales' => $this->supportedLocales,
            'default_locale' => $this->defaultLocale,
        ];
    }
}