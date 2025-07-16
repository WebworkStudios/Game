<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;

/**
 * Home Action - Hauptseite des KickersCup Managers
 *
 * Zeigt die Hauptnavigation, aktuelle Spielinformationen und Dashboard-Übersicht.
 * Folgt den ADR (Action-Domain-Responder) Prinzipien mit cleaner Dependency Injection.
 */
#[Route(path: '/', methods: ['GET'], name: 'home')]
class HomeAction
{
    public function __construct(
        private readonly ResponseFactory $responseFactory
    ) {}

    public function __invoke(Request $request): Response
    {
        $dashboardData = $this->prepareDashboardData();

        return $this->responseFactory->view('pages/home', $dashboardData);
    }

    /**
     * Bereitet die Dashboard-Daten für die Hauptseite vor
     */
    private function prepareDashboardData(): array
    {
        return [
            'app_name' => 'KickersCup Manager',
            'app_version' => '1.0.0',
            'welcome_message' => 'Willkommen in deinem Fußball-Manager!',

            // Benutzer-Informationen
            'user' => [
                'name' => 'Max Mustermann',
                'email' => 'max.mustermann@kickerscup.de',
                'is_admin' => true,
                'team' => [
                    'name' => 'FC Barcelona',
                    'league' => 'La Liga',
                    'position' => 2,
                    'points' => 68
                ]
            ],

            // Aktuelle Saison-Statistiken
            'season_stats' => [
                'current_matchday' => 28,
                'total_matchdays' => 38,
                'next_match' => [
                    'opponent' => 'Real Madrid',
                    'date' => '2024-03-15',
                    'time' => '21:00',
                    'venue' => 'Camp Nou'
                ],
                'last_result' => [
                    'opponent' => 'Athletic Bilbao',
                    'result' => '3:1',
                    'date' => '2024-03-08'
                ]
            ],

            // Framework-Features für Entwickler-Dashboard
            'framework_features' => [
                [
                    'icon' => '⚡',
                    'title' => 'Performance',
                    'description' => 'Route-Caching und Lazy Loading',
                    'status' => 'active',
                    'color' => 'success'
                ],
                [
                    'icon' => '🎯',
                    'title' => 'Modern PHP',
                    'description' => 'PHP 8.4 Features & Attributes',
                    'status' => 'active',
                    'color' => 'primary'
                ],
                [
                    'icon' => '🎨',
                    'title' => 'Template Engine',
                    'description' => 'Twig-ähnliche Syntax mit Caching',
                    'status' => 'active',
                    'color' => 'info'
                ],
                [
                    'icon' => '🏗️',
                    'title' => 'Domain-Driven Design',
                    'description' => 'Bounded Contexts & Clean Architecture',
                    'status' => 'active',
                    'color' => 'warning'
                ]
            ],

            // Schnell-Navigation
            'quick_actions' => [
                [
                    'title' => 'Kader verwalten',
                    'description' => 'Spieler und Aufstellungen',
                    'url' => '/team',
                    'icon' => '👥'
                ],
                [
                    'title' => 'Transfermarkt',
                    'description' => 'Spieler kaufen und verkaufen',
                    'url' => '/transfers',
                    'icon' => '💰'
                ],
                [
                    'title' => 'Taktik',
                    'description' => 'Formation und Spielweise',
                    'url' => '/tactics',
                    'icon' => '⚽'
                ],
                [
                    'title' => 'Liga-Tabelle',
                    'description' => 'Aktuelle Standings',
                    'url' => '/league',
                    'icon' => '🏆'
                ]
            ],

            // Neueste Meldungen/Aktivitäten
            'recent_activities' => [
                [
                    'type' => 'transfer',
                    'message' => 'Pedri hat bis 2028 verlängert',
                    'timestamp' => '2024-03-10 14:30:00',
                    'icon' => '✍️'
                ],
                [
                    'type' => 'match',
                    'message' => 'Sieg gegen Athletic Bilbao (3:1)',
                    'timestamp' => '2024-03-08 22:45:00',
                    'icon' => '⚽'
                ],
                [
                    'type' => 'injury',
                    'message' => 'Gavi kehrt ins Training zurück',
                    'timestamp' => '2024-03-07 09:15:00',
                    'icon' => '🏥'
                ]
            ]
        ];
    }
}