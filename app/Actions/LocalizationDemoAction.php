<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

#[Route(path: '/test/localization', methods: ['GET', 'POST'], name: 'test.localization')]
class LocalizationDemoAction
{
    private const array SUPPORTED_LOCALES = ['de', 'en', 'fr', 'es'];
    private const string DEFAULT_LOCALE = 'de';

    public function __construct(
        private readonly Application $app
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        // Handle language change with full security validation
        if ($request->isPost() && $request->input('change_language')) {
            return $this->handleLanguageChange($request);
        }

        // WICHTIG: Sprache aus Session laden BEVOR Demo-Daten geladen werden
        $this->ensureCorrectLocale();

        // Clear caches in debug mode for development
        if ($this->app->isDebug()) {
            $this->app->clearCaches();
        }

        // Render localization demo page
        return Response::view('pages/test/localization', [
            'current_locale' => $this->getCurrentLocale(),
            'demo_data' => $this->getDemoData(),
            'detection_info' => $this->getDetectionInfo($request),
            'translator_stats' => $this->getTranslatorStats(),
        ]);
    }

    /**
     * Ensure translator has correct locale from session
     */
    private function ensureCorrectLocale(): void
    {
        try {
            $detector = \Framework\Core\ServiceRegistry::get(\Framework\Localization\LanguageDetector::class);
            $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);

            // Get stored user locale from session
            $sessionLocale = $detector->getUserLocale();

            if ($sessionLocale && $sessionLocale !== $translator->getLocale()) {
                // Session has different locale than translator - sync them
                $translator->setLocale($sessionLocale);
                $translator->clearCache(); // Clear cache to load new language files
            }
        } catch (\Throwable $e) {
            // Log but don't break the application
            error_log("Failed to sync translator locale: " . $e->getMessage());
        }
    }

    /**
     * Handle language change with complete security validation
     */
    private function handleLanguageChange(Request $request): Response
    {
        try {
            // 1. CSRF Token Validation
            $csrf = \Framework\Core\ServiceRegistry::get(\Framework\Security\Csrf::class);
            $token = $request->input('_token', '');

            if (!$csrf->isValidToken($token)) {
                $this->logSecurityViolation($request, 'CSRF validation failed for localization change');

                if ($request->expectsJson()) {
                    return Response::json([
                        'error' => 'Security validation failed',
                        'message' => 'CSRF token is invalid or expired'
                    ], HttpStatus::PAGE_EXPIRED);
                }

                // Flash message über Session setzen
                $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);
                $session->flashError('Security validation failed. Please refresh the page and try again.');

                return Response::redirect('/test/localization');
            }

            // 2. Input Validation
            $newLocale = trim($request->input('locale', ''));
            if (!$this->isValidLocale($newLocale)) {
                $this->logSecurityViolation($request, "Invalid locale attempted: {$newLocale}");

                $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);
                $session->flashError('Invalid language selection.');

                return Response::redirect('/test/localization');
            }

            // 3. Perform Language Change
            $this->changeLanguage($newLocale);

            // 4. Regenerate CSRF token for security
            $csrf->refreshToken();

            // 5. Success Response
            if ($request->expectsJson()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Language changed successfully',
                    'locale' => $newLocale
                ]);
            }

            $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);
            $session->flashSuccess('Language changed successfully.');

            return Response::redirect('/test/localization');

        } catch (\Framework\Security\CsrfException $e) {
            $this->logSecurityViolation($request, "CSRF Exception: {$e->getMessage()}");

            $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);
            $session->flashError('Security validation failed. Please try again.');

            return Response::redirect('/test/localization');

        } catch (\InvalidArgumentException $e) {
            $this->logSecurityViolation($request, "Invalid argument: {$e->getMessage()}");

            $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);
            $session->flashError('Invalid language selection.');

            return Response::redirect('/test/localization');

        } catch (\Throwable $e) {
            // Log detailed error for debugging (but don't expose to user)
            error_log(sprintf(
                'Language change failed: %s in %s:%d. Request: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                json_encode([
                    'ip' => $request->ip(),
                    'user_agent' => $request->getUserAgent(),
                    'locale' => $request->input('locale', 'unknown')
                ])
            ));

            $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);
            $session->flashError('Language change failed. Please try again.');

            return Response::redirect('/test/localization');
        }
    }

    /**
     * Change the application language
     */
    private function changeLanguage(string $locale): void
    {
        // Update language detector (stores in session)
        $detector = \Framework\Core\ServiceRegistry::get(\Framework\Localization\LanguageDetector::class);
        $detector->setUserLocale($locale);

        // Update current translator instance
        $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
        $translator->setLocale($locale);

        // WICHTIG: Cache clearen, damit neue Sprache geladen wird
        $translator->clearCache();
    }

    /**
     * Validate locale against supported locales
     */
    private function isValidLocale(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true);
    }

    /**
     * Log security violations for monitoring
     */
    private function logSecurityViolation(Request $request, string $message): void
    {
        error_log(sprintf(
            'SECURITY VIOLATION - %s. IP: %s, User-Agent: %s, Referer: %s',
            $message,
            $request->ip(),
            $request->getUserAgent() ?? 'unknown',
            $request->getHeader('Referer') ?? 'unknown'
        ));
    }

    /**
     * Get current application locale
     */
    private function getCurrentLocale(): string
    {
        try {
            $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
            return $translator->getLocale();
        } catch (\Throwable) {
            return self::DEFAULT_LOCALE;
        }
    }

    /**
     * Get demo translations data
     */
    private function getDemoData(): array
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return $this->getFallbackDemoData();
        }

        return [
            // Navigation translations
            'navigation' => [
                ['key' => 'common.welcome', 'translated' => $translator->translate('common.welcome')],
                ['key' => 'common.navigation.home', 'translated' => $translator->translate('common.navigation.home')],
                ['key' => 'common.navigation.team', 'translated' => $translator->translate('common.navigation.team')],
                ['key' => 'common.navigation.matches', 'translated' => $translator->translate('common.navigation.matches')],
                ['key' => 'common.navigation.league', 'translated' => $translator->translate('common.navigation.league')],
                ['key' => 'common.navigation.profile', 'translated' => $translator->translate('common.navigation.profile')],
            ],

            // Authentication translations
            'auth' => [
                ['key' => 'auth.login', 'translated' => $translator->translate('auth.login')],
                ['key' => 'auth.password', 'translated' => $translator->translate('auth.password')],
                ['key' => 'auth.register', 'translated' => $translator->translate('auth.register')],
                ['key' => 'auth.logout', 'translated' => $translator->translate('auth.logout')],
                ['key' => 'auth.email', 'translated' => $translator->translate('auth.email')],
                ['key' => 'auth.remember_me', 'translated' => $translator->translate('auth.remember_me')],
            ],

            // Game-specific translations (simplified to avoid translatePlural issues)
            'game_stats' => [
                ['key' => 'game.stats.goals', 'translated' => $translator->translate('game.stats.goals')],
                ['key' => 'game.stats.assists', 'translated' => $translator->translate('game.stats.assists')],
                ['key' => 'game.stats.wins', 'translated' => $translator->translate('game.stats.wins')],
                ['key' => 'game.stats.losses', 'translated' => $translator->translate('game.stats.losses')],
            ],

            // Match events translations
            'match_events' => [
                ['key' => 'match.events.goal', 'translated' => $translator->translate('match.events.goal')],
                ['key' => 'match.events.yellow_card', 'translated' => $translator->translate('match.events.yellow_card')],
                ['key' => 'match.events.red_card', 'translated' => $translator->translate('match.events.red_card')],
                ['key' => 'match.events.substitution', 'translated' => $translator->translate('match.events.substitution')],
            ],

            // Common actions
            'actions' => [
                ['key' => 'common.actions.save', 'translated' => $translator->translate('common.actions.save')],
                ['key' => 'common.actions.cancel', 'translated' => $translator->translate('common.actions.cancel')],
                ['key' => 'common.actions.delete', 'translated' => $translator->translate('common.actions.delete')],
                ['key' => 'common.actions.edit', 'translated' => $translator->translate('common.actions.edit')],
            ],

            // Language names
            'language_names' => [
                'de' => $translator->translate('common.languages.de'),
                'en' => $translator->translate('common.languages.en'),
                'fr' => $translator->translate('common.languages.fr'),
                'es' => $translator->translate('common.languages.es'),
            ],
        ];
    }

    /**
     * Get translator instance safely
     */
    private function getTranslator(): ?\Framework\Localization\Translator
    {
        try {
            return \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Fallback demo data when translator is not available
     */
    private function getFallbackDemoData(): array
    {
        return [
            'navigation' => [
                ['key' => 'common.welcome', 'translated' => 'Welcome (fallback)'],
                ['key' => 'common.navigation.home', 'translated' => 'Home (fallback)'],
                ['key' => 'common.navigation.team', 'translated' => 'Team (fallback)'],
                ['key' => 'common.navigation.matches', 'translated' => 'Matches (fallback)'],
            ],
            'auth' => [
                ['key' => 'auth.login', 'translated' => 'Login (fallback)'],
                ['key' => 'auth.password', 'translated' => 'Password (fallback)'],
            ],
            'game_stats' => [
                ['key' => 'game.stats.goals', 'translated' => 'Goals (fallback)'],
            ],
            'match_events' => [
                ['key' => 'match.events.goal', 'translated' => 'Goal (fallback)'],
            ],
            'actions' => [
                ['key' => 'common.actions.save', 'translated' => 'Save (fallback)'],
            ],
            'language_names' => [
                'de' => 'Deutsch (fallback)',
                'en' => 'English (fallback)',
                'fr' => 'Français (fallback)',
                'es' => 'Español (fallback)',
            ],
        ];
    }

    /**
     * Get language detection information
     */
    private function getDetectionInfo(Request $request): array
    {
        return [
            'detected_locale' => $this->getCurrentLocale(),
            'accept_header' => $request->getHeader('Accept-Language', 'not-provided'),
            'default_locale' => self::DEFAULT_LOCALE,
            'available_locales' => self::SUPPORTED_LOCALES,
            'session_locale' => $this->getSessionLocale(),
        ];
    }

    /**
     * Get locale from session
     */
    private function getSessionLocale(): ?string
    {
        try {
            $detector = \Framework\Core\ServiceRegistry::get(\Framework\Localization\LanguageDetector::class);
            return $detector->getUserLocale();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get translator statistics
     */
    private function getTranslatorStats(): array
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return [
                'current_locale' => 'not-available',
                'loaded_namespaces' => 0,
                'cached_translations' => 0,
                'fallback_locale' => self::DEFAULT_LOCALE,
                'supported_locales' => self::SUPPORTED_LOCALES,
            ];
        }

        return [
            'current_locale' => $translator->getLocale(),
            'loaded_namespaces' => count($translator->getSupportedLocales() ?? []),
            'cached_translations' => 'filter-optimized',
            'fallback_locale' => self::DEFAULT_LOCALE,
            'supported_locales' => self::SUPPORTED_LOCALES,
        ];
    }
}