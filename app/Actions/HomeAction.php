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
 * UPDATED: Vollständige Dashboard-Daten für Template
 */
#[Route(path: '/', methods: ['GET'], name: 'home')]
class HomeAction
{
    public function __construct(
        private readonly ResponseFactory $responseFactory
    )
    {
    }

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
            'app_version' => '2.0.0',
            'welcome_message' => 'Willkommen in deinem Fußball-Manager!',

            // User-Daten
            'user' => $this->getUserData(),

            // Team-Daten
            'team_performance' => $this->getTeamPerformanceData(),

            // Saison-Statistiken
            'season_stats' => $this->getSeasonStats(),

            // Liga-Tabelle
            'league_table' => $this->getLeagueTable(),
        ];
    }

    /**
     * User-Daten für Dashboard
     */
    private function getUserData(): array
    {
        return [
            'name' => 'Max Mustermann',
            'is_admin' => false,
            'is_premium' => true,
            'team' => [
                'name' => 'FC Barcelona',
                'position' => 3,
                'league' => 'La Liga',
                'points' => 45,
            ],
            'profile' => [
                'coins' => 1250000,
                'gems' => 150,
            ]
        ];
    }

    /**
     * Team-Performance-Daten
     */
    private function getTeamPerformanceData(): array
    {
        return [
            'goals_scored' => 42,
            'goals_conceded' => 18,
            'clean_sheets' => 8,
            'wins' => 14,
            'draws' => 3,
            'losses' => 2,
        ];
    }

    /**
     * Saison-Statistiken
     */
    private function getSeasonStats(): array
    {
        return [
            'current_matchday' => 19,
            'total_matchdays' => 38,
            'next_match' => [
                'opponent' => 'Real Madrid',
                'date' => '2025-07-20',
                'time' => '20:00',
                'venue' => 'Camp Nou (Home)',
                'importance' => 'high',
                'prediction' => [
                    'home_win' => 45,
                    'draw' => 30,
                    'away_win' => 25,
                ]
            ],
            'last_results' => [
                [
                    'opponent' => 'Atletico Madrid',
                    'result' => '2-1',
                    'date' => '2025-07-12',
                    'is_home' => true,
                    'scorers' => ['Messi 23\'', 'Pedri 67\'']
                ],
                [
                    'opponent' => 'Valencia',
                    'result' => '3-0',
                    'date' => '2025-07-08',
                    'is_home' => false,
                    'scorers' => ['Lewandowski 15\'', 'Raphinha 34\'', 'Gavi 78\'']
                ],
                [
                    'opponent' => 'Sevilla',
                    'result' => '1-1',
                    'date' => '2025-07-05',
                    'is_home' => true,
                    'scorers' => ['Dembélé 56\'']
                ]
            ]
        ];
    }

    /**
     * Liga-Tabelle (Top 5)
     */
    private function getLeagueTable(): array
    {
        return [
            [
                'position' => 1,
                'team' => 'Real Madrid',
                'played' => 19,
                'wins' => 15,
                'draws' => 3,
                'losses' => 1,
                'goal_difference' => 25,
                'points' => 48
            ],
            [
                'position' => 2,
                'team' => 'Atletico Madrid',
                'played' => 19,
                'wins' => 14,
                'draws' => 4,
                'losses' => 1,
                'goal_difference' => 18,
                'points' => 46
            ],
            [
                'position' => 3,
                'team' => 'FC Barcelona',
                'played' => 19,
                'wins' => 14,
                'draws' => 3,
                'losses' => 2,
                'goal_difference' => 24,
                'points' => 45
            ],
            [
                'position' => 4,
                'team' => 'Real Sociedad',
                'played' => 19,
                'wins' => 11,
                'draws' => 5,
                'losses' => 3,
                'goal_difference' => 8,
                'points' => 38
            ],
            [
                'position' => 5,
                'team' => 'Villarreal',
                'played' => 19,
                'wins' => 10,
                'draws' => 6,
                'losses' => 3,
                'goal_difference' => 7,
                'points' => 36
            ]
        ];
    }
}