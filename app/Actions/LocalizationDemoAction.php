<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

#[Route(path: '/test/localization', methods: ['GET', 'POST'], name: 'test.localization')]
class LocalizationDemoAction
{
    public function __construct(
        private readonly Application $app
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        // Clear caches in debug mode
        if ($this->app->isDebug()) {
            $this->app->clearCaches();
        }

        // Handle language change
        if ($request->isPost() && $request->input('change_language')) {
            $newLocale = $request->input('locale', 'de');

            try {
                $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
                $translator->setLocale($newLocale);

                // Redirect to avoid POST resubmission
                return Response::redirect('/test/localization');
            } catch (\Throwable) {
                // Translator not available, continue with demo
            }
        }

        return Response::view('pages/test/localization', [
            'current_locale' => $this->getCurrentLocale(),
            'demo_data' => $this->getDemoData(),
            'detection_info' => $this->getDetectionInfo($request),
            'translator_stats' => $this->getTranslatorStats(),
        ]);
    }

    private function getCurrentLocale(): string
    {
        try {
            $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
            return $translator->getLocale();
        } catch (\Throwable) {
            return 'de'; // Fallback
        }
    }

    private function getDemoData(): array
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return $this->getFallbackDemoData();
        }

        return [
            // Navigation translations using filter syntax internally
            'navigation' => [
                ['key' => 'common.welcome', 'translated' => $translator->translate('common.welcome')],
                ['key' => 'common.navigation.home', 'translated' => $translator->translate('common.navigation.home')],
                ['key' => 'common.navigation.team', 'translated' => $translator->translate('common.navigation.team')],
                ['key' => 'common.navigation.matches', 'translated' => $translator->translate('common.navigation.matches')],
            ],

            // Authentication translations
            'auth' => [
                ['key' => 'auth.login', 'translated' => $translator->translate('auth.login')],
                ['key' => 'auth.password', 'translated' => $translator->translate('auth.password')],
                ['key' => 'auth.register', 'translated' => $translator->translate('auth.register')],
                ['key' => 'auth.logout', 'translated' => $translator->translate('auth.logout')],
            ],

            // Game statistics with pluralization - using filter logic
            'game_stats' => [
                [
                    'count' => 1,
                    'key' => 'game.goals',
                    'singular' => $translator->translatePlural('game.goals', 1, ['count' => 1]),
                    'plural' => $translator->translatePlural('game.goals', 3, ['count' => 3]),
                ],
                [
                    'count' => 5,
                    'key' => 'game.assists',
                    'singular' => $translator->translatePlural('game.assists', 1, ['count' => 1]),
                    'plural' => $translator->translatePlural('game.assists', 5, ['count' => 5]),
                ],
                [
                    'count' => 11,
                    'key' => 'game.players',
                    'singular' => $translator->translatePlural('game.players', 1, ['count' => 1]),
                    'plural' => $translator->translatePlural('game.players', 11, ['count' => 11]),
                ],
            ],

            // Match events with parameters - using filter approach
            'match_events' => [
                [
                    'key' => 'match.goal_scored',
                    'translated' => $translator->translate('match.goal_scored', [
                        'player' => 'Lionel Messi',
                        'minute' => 45
                    ])
                ],
                [
                    'key' => 'match.match_started',
                    'translated' => $translator->translate('match.match_started')
                ],
                [
                    'key' => 'match.match_ended',
                    'translated' => $translator->translate('match.match_ended')
                ],
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

    private function getFallbackDemoData(): array
    {
        return [
            'navigation' => [
                ['key' => 'common.welcome', 'translated' => 'Welcome (fallback)'],
                ['key' => 'common.navigation.home', 'translated' => 'Home (fallback)'],
            ],
            'auth' => [
                ['key' => 'auth.login', 'translated' => 'Login (fallback)'],
            ],
            'game_stats' => [],
            'match_events' => [],
            'language_names' => ['de' => 'Deutsch', 'en' => 'English'],
        ];
    }

    private function getDetectionInfo(Request $request): array
    {
        return [
            'detected_locale' => $this->getCurrentLocale(),
            'accept_header' => $request->getHeader('Accept-Language', 'not-provided'),
            'default_locale' => 'de',
            'available_locales' => ['de', 'en', 'fr', 'es'],
        ];
    }

    private function getTranslatorStats(): array
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return [
                'current_locale' => 'not-available',
                'loaded_namespaces' => 0,
                'cached_translations' => 0,
            ];
        }

        return [
            'current_locale' => $translator->getLocale(),
            'loaded_namespaces' => count($translator->getSupportedLocales()),
            'cached_translations' => 'filter-optimized',
        ];
    }

    private function getTranslator(): ?\Framework\Localization\Translator
    {
        try {
            return \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
        } catch (\Throwable) {
            return null;
        }
    }
}