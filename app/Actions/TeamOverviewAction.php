<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Team Overview Action - Zeigt komplexe Template-Nutzung
 */
#[Route(path: '/team', methods: ['GET'], name: 'team.overview')]
#[Route(path: '/team/overview', methods: ['GET'], name: 'team.overview.full')]
class TeamOverviewAction
{
    public function __invoke(Request $request): Response
    {
        // Simuliere Team-Daten aus Datenbank
        $teamData = $this->getTeamData();

        return Response::view('pages/team/overview', $teamData);
    }

    private function getTeamData(): array
    {
        // Simuliere komplexe Team-Daten
        $players = [
            // Goalkeepers
            [
                'name' => 'Marc-André ter Stegen',
                'position' => 'Goalkeeper',
                'age' => 31,
                'goals' => 0,
                'assists' => 1,
                'rating' => 8.5,
                'injured' => false,
                'injury_until' => null,
            ],
            [
                'name' => 'Iñaki Peña',
                'position' => 'Goalkeeper',
                'age' => 24,
                'goals' => 0,
                'assists' => 0,
                'rating' => 7.2,
                'injured' => true,
                'injury_until' => '2024-02-15',
            ],

            // Defenders
            [
                'name' => 'Ronald Araújo',
                'position' => 'Defender',
                'age' => 24,
                'goals' => 3,
                'assists' => 2,
                'rating' => 8.1,
                'injured' => false,
                'injury_until' => null,
            ],
            [
                'name' => 'Jules Koundé',
                'position' => 'Defender',
                'age' => 25,
                'goals' => 2,
                'assists' => 4,
                'rating' => 7.9,
                'injured' => false,
                'injury_until' => null,
            ],
            [
                'name' => 'Alejandro Balde',
                'position' => 'Defender',
                'age' => 20,
                'goals' => 1,
                'assists' => 8,
                'rating' => 7.7,
                'injured' => true,
                'injury_until' => '2024-01-30',
            ],

            // Midfielders
            [
                'name' => 'Pedri',
                'position' => 'Midfielder',
                'age' => 21,
                'goals' => 8,
                'assists' => 12,
                'rating' => 8.9,
                'injured' => false,
                'injury_until' => null,
            ],
            [
                'name' => 'Gavi',
                'position' => 'Midfielder',
                'age' => 19,
                'goals' => 5,
                'assists' => 7,
                'rating' => 8.3,
                'injured' => false,
                'injury_until' => null,
            ],
            [
                'name' => 'Frenkie de Jong',
                'position' => 'Midfielder',
                'age' => 26,
                'goals' => 4,
                'assists' => 9,
                'rating' => 8.0,
                'injured' => true,
                'injury_until' => '2024-02-05',
            ],

            // Forwards
            [
                'name' => 'Robert Lewandowski',
                'position' => 'Forward',
                'age' => 35,
                'goals' => 22,
                'assists' => 6,
                'rating' => 9.1,
                'injured' => false,
                'injury_until' => null,
            ],
            [
                'name' => 'Raphinha',
                'position' => 'Forward',
                'age' => 27,
                'goals' => 12,
                'assists' => 14,
                'rating' => 8.2,
                'injured' => false,
                'injury_until' => null,
            ],
            [
                'name' => 'Ferran Torres',
                'position' => 'Forward',
                'age' => 24,
                'goals' => 7,
                'assists' => 5,
                'rating' => 7.4,
                'injured' => false,
                'injury_until' => null,
            ],
        ];

        // Gruppiere Spieler nach Position
        $positions = $this->groupPlayersByPosition($players);

        // Zähle verletzte Spieler
        $injuredCount = count(array_filter($players, fn($p) => $p['injured']));

        // Berechne Team-Statistiken
        $stats = $this->calculateTeamStats($players);

        // Simuliere letzte Spiele
        $recentMatches = $this->getRecentMatches();

        return [
            'team' => [
                'name' => 'FC Barcelona',
                'players' => $players,
                'positions' => $positions,
                'injured_count' => $injuredCount,
                'stats' => $stats,
                'recent_matches' => $recentMatches,
            ]
        ];
    }

    private function groupPlayersByPosition(array $players): array
    {
        $positions = [
            'Goalkeeper' => ['name' => 'Goalkeepers', 'players' => []],
            'Defender' => ['name' => 'Defenders', 'players' => []],
            'Midfielder' => ['name' => 'Midfielders', 'players' => []],
            'Forward' => ['name' => 'Forwards', 'players' => []],
        ];

        foreach ($players as $player) {
            $positions[$player['position']]['players'][] = $player;
        }

        return array_values($positions);
    }

    private function calculateTeamStats(array $players): array
    {
        $totalGoals = array_sum(array_column($players, 'goals'));
        $averageAge = round(array_sum(array_column($players, 'age')) / count($players), 1);

        return [
            'players_count' => count($players),
            'average_age' => $averageAge,
            'total_goals' => $totalGoals,
            'wins' => 18, // Simuliert
        ];
    }

    private function getRecentMatches(): array
    {
        return [
            [
                'home_team' => 'FC Barcelona',
                'away_team' => 'Real Madrid',
                'home_score' => 2,
                'away_score' => 1,
                'date' => '2024-01-20',
                'stadium' => 'Camp Nou',
                'attendance' => 85000,
            ],
            [
                'home_team' => 'Atletico Madrid',
                'away_team' => 'FC Barcelona',
                'home_score' => 0,
                'away_score' => 3,
                'date' => '2024-01-15',
                'stadium' => 'Wanda Metropolitano',
                'attendance' => 68000,
            ],
            [
                'home_team' => 'FC Barcelona',
                'away_team' => 'Valencia CF',
                'home_score' => 4,
                'away_score' => 0,
                'date' => '2024-01-10',
                'stadium' => 'Camp Nou',
                'attendance' => 82000,
            ],
        ];
    }
}