<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Localization Demo Action - Vollständige Neuimplementierung
 *
 * Features:
 * - Sichere CSRF-Validierung
 * - Robuste Sprachwechsel-Funktionalität
 * - Umfassendes Error Handling
 * - Debug-Informationen für Entwicklung
 */
#[Route(path: '/test/localization', methods: ['GET', 'POST'], name: 'test.localization')]
class LocalizationDemoAction
{
    private const array SUPPORTED_LOCALES = ['de', 'en', 'fr', 'es'];
    private const string DEFAULT_LOCALE = 'de';

    public function __construct(
        private readonly Application $app
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // Handle session test request
        if ($request->isPost() && $request->input('test_session')) {
            return $this->handleSessionTest($request);
        }

        // POST Request: Handle language change
        if ($request->isPost()) {
            return $this->handleLanguageChange($request);
        }

        // GET Request: Display localization demo
        return $this->displayDemo($request);
    }

    /**
     * Display the localization demo page
     */
    private function displayDemo(Request $request): Response
    {
        // Clear caches in debug mode
        if ($this->app->isDebug()) {
            $this->app->clearCaches();
        }

        // Collect all data for the demo
        $data = [
            'current_locale' => $this->getCurrentLocale(),
            'supported_locales' => self::SUPPORTED_LOCALES,
            'demo_translations' => $this->getDemoTranslations(),
            'detection_info' => $this->getDetectionInfo($request),
            'translator_stats' => $this->getTranslatorStats(),
            'session_info' => $this->getSessionInfo(),
            'csrf_debug' => $this->getCsrfDebugInfo(),
        ];

        return Response::view('pages/test/localization', $data);
    }

    /**
     * Handle language change requests with full validation
     */
    private function handleLanguageChange(Request $request): Response
    {
        try {
            // Step 1: Validate CSRF token
            $this->validateCsrfToken($request);

            // Step 2: Validate locale input
            $newLocale = $this->validateLocaleInput($request);

            // Step 3: Change language
            $this->performLanguageChange($newLocale);

            // Step 4: Return success response
            return $this->createSuccessResponse($request, $newLocale);

        } catch (\Framework\Security\CsrfException $e) {
            return $this->handleCsrfError($request, $e);
        } catch (\InvalidArgumentException $e) {
            return $this->handleValidationError($request, $e);
        } catch (\Throwable $e) {
            return $this->handleGenericError($request, $e);
        }
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrfToken(Request $request): void
    {
        $csrf = \Framework\Core\ServiceRegistry::get(\Framework\Security\Csrf::class);
        $token = $request->input('_token', '');

        // Enhanced logging for debugging
        if ($this->app->isDebug()) {
            error_log("CSRF Debug - Submitted: {$token}");
            error_log("CSRF Debug - Expected: " . $csrf->getToken());
            error_log("CSRF Debug - Valid: " . ($csrf->isValidToken($token) ? 'YES' : 'NO'));
        }

        if (!$csrf->isValidToken($token)) {
            $this->logSecurityViolation($request, 'CSRF validation failed');
            throw new \Framework\Security\CsrfException('Invalid CSRF token');
        }
    }

    /**
     * Validate locale input
     */
    private function validateLocaleInput(Request $request): string
    {
        $locale = trim($request->input('locale', ''));

        if (empty($locale)) {
            throw new \InvalidArgumentException('Locale parameter is required');
        }

        if (!$this->isValidLocale($locale)) {
            $this->logSecurityViolation($request, "Invalid locale attempted: {$locale}");
            throw new \InvalidArgumentException("Unsupported locale: {$locale}");
        }

        return $locale;
    }

    /**
     * Perform the actual language change
     */
    private function performLanguageChange(string $locale): void
    {
        if ($this->app->isDebug()) {
            error_log("Language change: {$this->getCurrentLocale()} -> {$locale}");
        }

        try {
            // Step 1: Get session and ensure it's properly started
            $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);
            if (!$session->isStarted()) {
                $session->start();
            }

            // Step 2: Update language detector (session storage)
            $detector = \Framework\Core\ServiceRegistry::get(\Framework\Localization\LanguageDetector::class);
            $detector->setUserLocale($locale);

            // Step 3: Update translator
            $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
            $translator->setLocale($locale);
            $translator->clearCache();

            // Step 4: Force session write and verify
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
                session_start();
            }

            // Step 5: Additional verification with direct session access
            if (isset($_SESSION)) {
                $_SESSION['locale'] = $locale;
                $_SESSION['language_changed_at'] = time();
            }

            // Verify changes
            if ($this->app->isDebug()) {
                error_log("Language change complete. New locale: " . $translator->getLocale());
                error_log("Session locale from detector: " . ($detector->getUserLocale() ?? 'null'));
                error_log("Direct session locale: " . ($_SESSION['locale'] ?? 'not set'));
                error_log("Session ID: " . session_id());
                error_log("Session status: " . session_status());
                error_log("Full session data: " . json_encode($_SESSION ?? []));
            }

        } catch (\Throwable $e) {
            error_log("Language change failed: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            throw new \RuntimeException("Failed to change language to {$locale}: " . $e->getMessage());
        }
    }

    /**
     * Create success response
     */
    private function createSuccessResponse(Request $request, string $locale): Response
    {
        $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);

        if ($request->expectsJson()) {
            return Response::json([
                'success' => true,
                'message' => 'Language changed successfully',
                'locale' => $locale,
                'redirect_url' => '/test/localization'
            ]);
        }

        $session->flashSuccess("Language changed to: {$locale}");
        return Response::redirect('/test/localization');
    }

    /**
     * Handle CSRF errors
     */
    private function handleCsrfError(Request $request, \Framework\Security\CsrfException $e): Response
    {
        $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);

        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Security validation failed',
                'message' => 'CSRF token is invalid or expired. Please refresh the page.',
                'code' => 'CSRF_INVALID'
            ], HttpStatus::PAGE_EXPIRED);
        }

        $session->flashError('Security validation failed. Please refresh the page and try again.');
        return Response::redirect('/test/localization');
    }

    /**
     * Handle validation errors
     */
    private function handleValidationError(Request $request, \InvalidArgumentException $e): Response
    {
        $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);

        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR'
            ], HttpStatus::BAD_REQUEST);
        }

        $session->flashError($e->getMessage());
        return Response::redirect('/test/localization');
    }

    /**
     * Handle generic errors
     */
    private function handleGenericError(Request $request, \Throwable $e): Response
    {
        // Log detailed error for debugging
        error_log(sprintf(
            'Localization error: %s in %s:%d. Request: %s',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            json_encode([
                'method' => $request->getMethod()->value,
                'ip' => $request->ip(),
                'user_agent' => $request->getUserAgent(),
                'locale' => $request->input('locale', 'unknown'),
                'csrf_token' => substr($request->input('_token', ''), 0, 8) . '...'
            ])
        ));

        $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);

        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred',
                'code' => 'INTERNAL_ERROR'
            ], HttpStatus::INTERNAL_SERVER_ERROR);
        }

        $session->flashError('Language change failed. Please try again.');
        return Response::redirect('/test/localization');
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
     * Check if locale is valid
     */
    private function isValidLocale(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true);
    }

    /**
     * Get demo translations for all categories
     */
    private function getDemoTranslations(): array
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return $this->getFallbackTranslations();
        }

        return [
            'navigation' => [
                ['key' => 'common.welcome', 'value' => $translator->translate('common.welcome')],
                ['key' => 'common.navigation.home', 'value' => $translator->translate('common.navigation.home')],
                ['key' => 'common.navigation.team', 'value' => $translator->translate('common.navigation.team')],
                ['key' => 'common.navigation.matches', 'value' => $translator->translate('common.navigation.matches')],
                ['key' => 'common.navigation.league', 'value' => $translator->translate('common.navigation.league')],
                ['key' => 'common.navigation.profile', 'value' => $translator->translate('common.navigation.profile')],
            ],
            'authentication' => [
                ['key' => 'auth.login', 'value' => $translator->translate('auth.login')],
                ['key' => 'auth.logout', 'value' => $translator->translate('auth.logout')],
                ['key' => 'auth.register', 'value' => $translator->translate('auth.register')],
                ['key' => 'auth.password', 'value' => $translator->translate('auth.password')],
                ['key' => 'auth.email', 'value' => $translator->translate('auth.email')],
            ],
            'validation' => [
                ['key' => 'validation.required', 'value' => $translator->translate('validation.required')],
                ['key' => 'validation.email', 'value' => $translator->translate('validation.email')],
                ['key' => 'validation.min_length', 'value' => $translator->translate('validation.min_length')],
                ['key' => 'validation.max_length', 'value' => $translator->translate('validation.max_length')],
            ],
            'game' => [
                ['key' => 'game.player', 'value' => $translator->translate('game.player')],
                ['key' => 'game.team', 'value' => $translator->translate('game.team')],
                ['key' => 'game.match', 'value' => $translator->translate('game.match')],
                ['key' => 'game.league', 'value' => $translator->translate('game.league')],
                ['key' => 'game.season', 'value' => $translator->translate('game.season')],
            ],
        ];
    }

    /**
     * Get detection information for debugging
     */
    private function getDetectionInfo(Request $request): array
    {
        $detector = $this->getLanguageDetector();

        return [
            'current_locale' => $this->getCurrentLocale(),
            'session_locale' => $detector?->getUserLocale(),
            'accept_language' => $request->getHeader('Accept-Language'),
            'supported_locales' => self::SUPPORTED_LOCALES,
            'default_locale' => self::DEFAULT_LOCALE,
            'detection_method' => $this->getDetectionMethod(),
        ];
    }

    /**
     * Get translator statistics
     */
    private function getTranslatorStats(): array
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return [
                'status' => 'unavailable',
                'error' => 'Translator service not accessible'
            ];
        }

        try {
            $stats = $translator->getCacheStats();
            $stats['status'] = 'available';
            return $stats;
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get session information for debugging
     */
    private function getSessionInfo(): array
    {
        try {
            $session = \Framework\Core\ServiceRegistry::get(\Framework\Security\Session::class);
            return [
                'session_id' => $session->getId(),
                'session_started' => $session->isStarted(),
                'locale_in_session' => $session->get('locale'),
                'flash_messages' => $session->getAllFlash(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => 'Session not accessible: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get CSRF debug information
     */
    private function getCsrfDebugInfo(): array
    {
        try {
            $csrf = \Framework\Core\ServiceRegistry::get(\Framework\Security\Csrf::class);
            return [
                'token_info' => $csrf->getTokenInfo(),
                'token_field' => $csrf->getTokenField(),
                'current_token' => $csrf->getToken(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => 'CSRF service not accessible: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get detection method used
     */
    private function getDetectionMethod(): string
    {
        $detector = $this->getLanguageDetector();

        if (!$detector) {
            return 'fallback';
        }

        if ($detector->getUserLocale()) {
            return 'session';
        }

        return 'default';
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
     * Get language detector instance safely
     */
    private function getLanguageDetector(): ?\Framework\Localization\LanguageDetector
    {
        try {
            return \Framework\Core\ServiceRegistry::get(\Framework\Localization\LanguageDetector::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get fallback translations when translator is unavailable
     */
    private function getFallbackTranslations(): array
    {
        return [
            'navigation' => [
                ['key' => 'common.welcome', 'value' => 'Welcome (Fallback)'],
                ['key' => 'common.navigation.home', 'value' => 'Home (Fallback)'],
                ['key' => 'common.navigation.team', 'value' => 'Team (Fallback)'],
            ],
            'authentication' => [
                ['key' => 'auth.login', 'value' => 'Login (Fallback)'],
                ['key' => 'auth.logout', 'value' => 'Logout (Fallback)'],
            ],
            'validation' => [
                ['key' => 'validation.required', 'value' => 'Required (Fallback)'],
            ],
            'game' => [
                ['key' => 'game.player', 'value' => 'Player (Fallback)'],
                ['key' => 'game.team', 'value' => 'Team (Fallback)'],
            ],
        ];
    }

    /**
     * Handle session test requests
     */
    private function handleSessionTest(Request $request): Response
    {
        try {
            // Force session start if not started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Set test data
            $_SESSION['test_' . time()] = 'Session test at ' . date('Y-m-d H:i:s');

            $sessionInfo = [
                'session_id' => session_id(),
                'session_status' => session_status(),
                'session_data_count' => count($_SESSION),
                'cookie_params' => session_get_cookie_params(),
                'save_path' => session_save_path(),
                'headers_sent' => headers_sent($file, $line) ? "Yes (in $file:$line)" : 'No',
                'session_data' => $_SESSION,
            ];

            // Force cookie to be sent
            if (!headers_sent()) {
                header('Set-Cookie: test_cookie=test_value; Path=/; HttpOnly');
            }

            return Response::json([
                'success' => true,
                'message' => 'Session test completed',
                'debug' => $sessionInfo,
            ]);

        } catch (\Throwable $e) {
            return Response::json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Log security violations
     */
    private function logSecurityViolation(Request $request, string $message): void
    {
        error_log(sprintf(
            'SECURITY VIOLATION [Localization]: %s | IP: %s | UA: %s | Referer: %s',
            $message,
            $request->ip(),
            $request->getUserAgent() ?? 'unknown',
            $request->getHeader('Referer') ?? 'none'
        ));
    }
}