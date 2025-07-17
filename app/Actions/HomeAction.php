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
 * UPDATED: Für neue SRP-konforme TemplateEngine optimiert
 *
 * Zeigt die Hauptnavigation, aktuelle Spielinformationen und Dashboard-Übersicht.
 * Demonstriert die erweiterten Features der neuen TemplateEngine.
 */
#[Route(path: '/', methods: ['GET'], name: 'home')]
class HomeAction
{
    public function __construct(
        private readonly ResponseFactory $responseFactory
    ) {
        // Debug: Überprüfe ob Constructor aufgerufen wird
        error_log('HomeAction constructor called');
    }

    public function __invoke(Request $request): Response
    {
        error_log('HomeAction __invoke called');
        $dashboardData = $this->prepareDashboardData();

        return $this->responseFactory->view('pages/home', $dashboardData);
    }

    /**
     * Bereitet die Dashboard-Daten für die Hauptseite vor
     *
     * ENHANCED: Erweiterte Datenstruktur für neue TemplateEngine-Features
     */
    private function prepareDashboardData(): array
    {
        return [
            'app_name' => 'KickersCup Manager',
            'app_version' => '2.0.0',
            'welcome_message' => 'Willkommen in deinem Fußball-Manager!',

            // Benutzer-Informationen (erweitert)
            'user' => [
                'name' => 'Max Mustermann',
                'email' => 'max.mustermann@kickerscup.de',
                'is_admin' => true,
                'is_premium' => false,
                'profile' => [
                    'avatar' => '/assets/images/avatars/default.png',
                    'level' => 15,
                    'experience' => 2850,
                    'coins' => 125000,
                    'gems' => 75
                ],
                'team' => [
                    'name' => 'FC Barcelona',
                    'league' => 'La Liga',
                    'position' => 2,
                    'points' => 68,
                    'form' => ['W', 'W', 'D', 'W', 'L'], // Letzte 5 Spiele
                    'stadium' => [
                        'name' => 'Camp Nou',
                        'capacity' => 99354,
                        'attendance_avg' => 85000
                    ]
                ]
            ],

            // Aktuelle Saison-Statistiken (erweitert)
            'season_stats' => [
                'current_matchday' => 28,
                'total_matchdays' => 38,
                'completion_percentage' => 74, // 28/38 * 100
                'next_match' => [
                    'opponent' => 'Real Madrid',
                    'opponent_logo' => '/assets/images/teams/real-madrid.png',
                    'date' => '2024-03-15',
                    'time' => '21:00',
                    'venue' => 'Camp Nou',
                    'is_home' => true,
                    'importance' => 'high', // high, medium, low
                    'prediction' => [
                        'home_win' => 45,
                        'draw' => 30,
                        'away_win' => 25
                    ]
                ],
                'last_results' => [
                    [
                        'opponent' => 'Athletic Bilbao',
                        'result' => '3:1',
                        'date' => '2024-03-08',
                        'is_home' => true,
                        'scorers' => ['Lewandowski', 'Pedri', 'Raphinha']
                    ],
                    [
                        'opponent' => 'Atletico Madrid',
                        'result' => '2:2',
                        'date' => '2024-03-01',
                        'is_home' => false,
                        'scorers' => ['Gavi', 'Ferran Torres']
                    ]
                ]
            ],

            // Liga-Tabelle (Top 5)
            'league_table' => [
                [
                    'position' => 1,
                    'team' => 'Real Madrid',
                    'points' => 72,
                    'played' => 28,
                    'wins' => 22,
                    'draws' => 6,
                    'losses' => 0,
                    'goal_difference' => 45
                ],
                [
                    'position' => 2,
                    'team' => 'FC Barcelona',
                    'points' => 68,
                    'played' => 28,
                    'wins' => 21,
                    'draws' => 5,
                    'losses' => 2,
                    'goal_difference' => 38
                ],
                [
                    'position' => 3,
                    'team' => 'Atletico Madrid',
                    'points' => 58,
                    'played' => 28,
                    'wins' => 17,
                    'draws' => 7,
                    'losses' => 4,
                    'goal_difference' => 22
                ],
                [
                    'position' => 4,
                    'team' => 'Athletic Bilbao',
                    'points' => 52,
                    'played' => 28,
                    'wins' => 15,
                    'draws' => 7,
                    'losses' => 6,
                    'goal_difference' => 8
                ],
                [
                    'position' => 5,
                    'team' => 'Real Sociedad',
                    'points' => 49,
                    'played' => 28,
                    'wins' => 14,
                    'draws' => 7,
                    'losses' => 7,
                    'goal_difference' => 5
                ]
            ],

            // Team-Performance (für Charts/Widgets)
            'team_performance' => [
                'goals_scored' => 78,
                'goals_conceded' => 40,
                'clean_sheets' => 12,
                'top_scorer' => [
                    'name' => 'Robert Lewandowski',
                    'goals' => 25,
                    'assists' => 8
                ],
                'best_player' => [
                    'name' => 'Pedri González',
                    'rating' => 8.7,
                    'appearances' => 26
                ],
                'injuries' => [
                    [
                        'player' => 'Ousmane Dembélé',
                        'injury' => 'Hamstring',
                        'return_date' => '2024-03-20'
                    ],
                    [
                        'player' => 'Frenkie de Jong',
                        'injury' => 'Ankle',
                        'return_date' => '2024-03-25'
                    ]
                ]
            ],

            // Transfers & Markt
            'transfers' => [
                'incoming' => [
                    [
                        'player' => 'João Félix',
                        'from' => 'Atletico Madrid',
                        'fee' => 80000000,
                        'type' => 'permanent'
                    ]
                ],
                'outgoing' => [
                    [
                        'player' => 'Ansu Fati',
                        'to' => 'Brighton',
                        'fee' => 0,
                        'type' => 'loan'
                    ]
                ],
                'targets' => [
                    [
                        'player' => 'Kylian Mbappé',
                        'club' => 'PSG',
                        'interest_level' => 'high',
                        'estimated_fee' => 150000000
                    ],
                    [
                        'player' => 'Erling Haaland',
                        'club' => 'Manchester City',
                        'interest_level' => 'medium',
                        'estimated_fee' => 200000000
                    ]
                ]
            ],

            // Notifications & Alerts
            'notifications' => [
                [
                    'type' => 'match',
                    'icon' => '⚽',
                    'message' => 'Nächstes Spiel gegen Real Madrid in 3 Tagen',
                    'priority' => 'high',
                    'time' => '2024-03-12 09:00'
                ],
                [
                    'type' => 'injury',
                    'icon' => '🏥',
                    'message' => 'Dembélé wird 2 Wochen ausfallen',
                    'priority' => 'medium',
                    'time' => '2024-03-10 14:30'
                ],
                [
                    'type' => 'transfer',
                    'icon' => '💰',
                    'message' => 'Transferangebot für Pedri erhalten',
                    'priority' => 'low',
                    'time' => '2024-03-09 11:15'
                ]
            ],

            // Framework-Features (für Template-Engine Demo)
            'framework_features' => [
                [
                    'icon' => '🚀',
                    'title' => 'SRP-konforme TemplateEngine',
                    'description' => 'Neue modulare Architektur mit besserer Performance',
                    'status' => 'active',
                    'performance_gain' => 35
                ],
                [
                    'icon' => '🎯',
                    'title' => 'Token-basiertes Parsing',
                    'description' => 'Typsichere Template-Token für bessere Fehlerbehandlung',
                    'status' => 'active',
                    'performance_gain' => 20
                ],
                [
                    'icon' => '⚡',
                    'title' => 'Erweiterte Cache-Funktionen',
                    'description' => 'Template-Caching mit Dependency-Tracking',
                    'status' => 'active',
                    'performance_gain' => 50
                ],
                [
                    'icon' => '🔧',
                    'title' => 'Erweiterte Filter-Pipeline',
                    'description' => 'Modulares Filter-System mit besserer Erweiterbarkeit',
                    'status' => 'active',
                    'performance_gain' => 15
                ]
            ],

            // Template-Engine Test Data
            'template_engine_demo' => [
                'simple_variable' => 'Hello World!',
                'number' => 42,
                'boolean_true' => true,
                'boolean_false' => false,
                'null_value' => null,
                'array_simple' => ['Apple', 'Banana', 'Cherry'],
                'array_empty' => [],
                'nested_data' => [
                    'level1' => [
                        'level2' => [
                            'level3' => 'Deep nested value'
                        ]
                    ]
                ],
                'condition_tests' => [
                    'show_admin_panel' => true,
                    'maintenance_mode' => false,
                    'feature_enabled' => true
                ]
            ]
        ];
    }
}
