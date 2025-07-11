<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Localization\LanguageDetector;
use Framework\Localization\Translator;
use Framework\Routing\Route;

/**
 * Localization Demo Action - Demonstriert alle Mehrsprachigkeits-Features
 */
#[Route(path: '/test/localization', methods: ['GET', 'POST'], name: 'test.localization')]
#[Route(path: '/test/i18n', methods: ['GET', 'POST'], name: 'test.i18n')]
class LocalizationDemoAction
{
    public function __construct(
        private readonly Application $app
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        $translator = $this->app->getContainer()->get(Translator::class);
        $detector = $this->app->getContainer()->get(LanguageDetector::class);

        // Handle language change
        if ($request->isPost() && $request->has('change_language')) {
            $newLocale = $request->input('locale');

            if ($newLocale && $detector->isValidLocale($newLocale)) {
                $detector->setUserLocale($newLocale);
                $translator->setLocale($newLocale);

                // Redirect to prevent form resubmission
                return Response::redirect('/test/localization');
            }
        }

        // Auto-detect language from request
        $detectedLocale = $detector->detectLocale($request);
        $translator->setLocale($detectedLocale);

        // Prepare demo data
        $data = [
            'current_locale' => $translator->getLocale(),
            'supported_locales' => $translator->getSupportedLocales(),
            'detection_info' => $detector->getDetectionInfo($request),
            'translator_stats' => $translator->getCacheStats(),
            'demo_data' => $this->getDemoData($translator),
            'example_translations' => $this->getExampleTranslations($translator),
            'csrf_token' => $this->app->getCsrf()->getToken(),
        ];

        return Response::view('pages/test/localization', $data);
    }

    /**
     * Get demo data for different content types
     */
    private function getDemoData(Translator $translator): array
    {
        return [
            // Navigation items
            'navigation' => [
                ['key' => 'common.navigation.home', 'translated' => $translator->t('common.navigation.home')],
                ['key' => 'common.navigation.team', 'translated' => $translator->t('common.navigation.team')],
                ['key' => 'common.navigation.matches', 'translated' => $translator->t('common.navigation.matches')],
                ['key' => 'common.navigation.league', 'translated' => $translator->t('common.navigation.league')],
            ],

            // Authentication
            'auth' => [
                ['key' => 'auth.login', 'translated' => $translator->t('auth.login')],
                ['key' => 'auth.logout', 'translated' => $translator->t('auth.logout')],
                ['key' => 'auth.register', 'translated' => $translator->t('auth.register')],
                ['key' => 'auth.password', 'translated' => $translator->t('auth.password')],
            ],

            // Game statistics with pluralization
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

            // Match events with parameters
            'match_events' => [
                [
                    'key' => 'match.goal_scored',
                    'translated' => $translator->t('match.goal_scored', [
                        'player' => 'Lionel Messi',
                        'minute' => 45
                    ])
                ],
                [
                    'key' => 'match.match_started',
                    'translated' => $translator->t('match.match_started')
                ],
                [
                    'key' => 'match.match_ended',
                    'translated' => $translator->t('match.match_ended')
                ],
            ],

            // Language names
            'language_names' => [
                'de' => $translator->t('common.languages.de'),
                'en' => $translator->t('common.languages.en'),
                'fr' => $translator->t('common.languages.fr'),
                'es' => $translator->t('common.languages.es'),
            ],
        ];
    }

    /**
     * Get example translations for template demonstration
     */
    private function getExampleTranslations(Translator $translator): array
    {
        return [
            // Template function examples
            'template_functions' => [
                "{{ t('common.welcome') }}" => $translator->t('common.welcome'),
                "{{ t('auth.login') }}" => $translator->t('auth.login'),
                "{{ t_plural('game.goals', 3) }}" => $translator->translatePlural('game.goals', 3, ['count' => 3]),
                "{{ locale() }}" => $translator->getLocale(),
            ],

            // Filter examples
            'template_filters' => [
                "{{ 'common.welcome'|t }}" => $translator->t('common.welcome'),
                "{{ 'auth.password'|t }}" => $translator->t('auth.password'),
                "{{ 'game.assists'|t_plural:7 }}" => $translator->translatePlural('game.assists', 7, ['count' => 7]),
            ],

            // Parameter examples
            'parameter_examples' => [
                [
                    'template' => "{{ t('match.goal_scored', {player: 'Messi', minute: 90}) }}",
                    'result' => $translator->t('match.goal_scored', ['player' => 'Messi', 'minute' => 90])
                ],
            ],
        ];
    }

    /**
     * Get translator statistics manually
     */
    private function getTranslatorStats(Translator $translator): array
    {
        return [
            'current_locale' => $translator->getLocale(),
            'fallback_locale' => $translator->getLocale(), // Fallback not exposed, use current
            'supported_locales' => count($translator->getSupportedLocales()),
            'loaded_namespaces' => 'N/A', // Not exposed in public API
            'cached_translations' => 'N/A', // Not exposed in public API
        ];
    }
}