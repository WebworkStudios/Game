<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Team Overview Action - Kaderübersicht von Torwart bis Sturm
 */
#[Route(path: '/team', methods: ['GET'], name: 'team.overview')]
#[Route(path: '/team/overview', methods: ['GET'], name: 'team.overview.full')]
class TeamOverviewAction
{
    public function __invoke(Request $request): Response
    {
        $teamData = $this->getTeamData();

        return Response::view('pages/team/overview', $teamData);
    }

    private function getTeamData(): array
    {
        // Mock-Daten für Kader - von Torwart bis Sturm
        $players = [
            // Torwart
            [
                'id' => 1,
                'name' => 'Marc-André ter Stegen',
                'position' => 'Torwart',
                'age' => 31,
                'shirt_number' => 1,
                'goals' => 0,
                'assists' => 1,
                'rating' => 8.5,
                'market_value' => 25000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2028-06-30',
                'games_played' => 28,
                'clean_sheets' => 15,
            ],
            [
                'id' => 2,
                'name' => 'Iñaki Peña',
                'position' => 'Torwart',
                'age' => 24,
                'shirt_number' => 13,
                'goals' => 0,
                'assists' => 0,
                'rating' => 7.2,
                'market_value' => 8000000,
                'injured' => true,
                'injury_until' => '2024-02-15',
                'contract_until' => '2026-06-30',
                'games_played' => 5,
                'clean_sheets' => 2,
            ],

            // Abwehr
            [
                'id' => 3,
                'name' => 'Ronald Araújo',
                'position' => 'Abwehr',
                'age' => 24,
                'shirt_number' => 4,
                'goals' => 3,
                'assists' => 2,
                'rating' => 8.1,
                'market_value' => 70000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 25,
                'clean_sheets' => 12,
            ],
            [
                'id' => 4,
                'name' => 'Jules Koundé',
                'position' => 'Abwehr',
                'age' => 25,
                'shirt_number' => 23,
                'goals' => 2,
                'assists' => 4,
                'rating' => 7.9,
                'market_value' => 60000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2027-06-30',
                'games_played' => 26,
                'clean_sheets' => 11,
            ],
            [
                'id' => 5,
                'name' => 'Alejandro Balde',
                'position' => 'Abwehr',
                'age' => 20,
                'shirt_number' => 3,
                'goals' => 1,
                'assists' => 8,
                'rating' => 7.7,
                'market_value' => 45000000,
                'injured' => true,
                'injury_until' => '2024-01-30',
                'contract_until' => '2028-06-30',
                'games_played' => 22,
                'clean_sheets' => 9,
            ],
            [
                'id' => 6,
                'name' => 'Andreas Christensen',
                'position' => 'Abwehr',
                'age' => 27,
                'shirt_number' => 15,
                'goals' => 1,
                'assists' => 1,
                'rating' => 7.6,
                'market_value' => 35000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 20,
                'clean_sheets' => 8,
            ],

            // Mittelfeld
            [
                'id' => 7,
                'name' => 'Pedri',
                'position' => 'Mittelfeld',
                'age' => 21,
                'shirt_number' => 8,
                'goals' => 8,
                'assists' => 12,
                'rating' => 8.9,
                'market_value' => 100000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 27,
                'clean_sheets' => 0,
            ],
            [
                'id' => 8,
                'name' => 'Gavi',
                'position' => 'Mittelfeld',
                'age' => 19,
                'shirt_number' => 6,
                'goals' => 5,
                'assists' => 7,
                'rating' => 8.3,
                'market_value' => 90000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 24,
                'clean_sheets' => 0,
            ],
            [
                'id' => 9,
                'name' => 'Frenkie de Jong',
                'position' => 'Mittelfeld',
                'age' => 26,
                'shirt_number' => 21,
                'goals' => 4,
                'assists' => 9,
                'rating' => 8.0,
                'market_value' => 80000000,
                'injured' => true,
                'injury_until' => '2024-02-05',
                'contract_until' => '2026-06-30',
                'games_played' => 19,
                'clean_sheets' => 0,
            ],
            [
                'id' => 10,
                'name' => 'Ilkay Gündogan',
                'position' => 'Mittelfeld',
                'age' => 33,
                'shirt_number' => 22,
                'goals' => 6,
                'assists' => 11,
                'rating' => 7.8,
                'market_value' => 25000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2025-06-30',
                'games_played' => 26,
                'clean_sheets' => 0,
            ],

            // Sturm
            [
                'id' => 11,
                'name' => 'Robert Lewandowski',
                'position' => 'Sturm',
                'age' => 35,
                'shirt_number' => 9,
                'goals' => 22,
                'assists' => 6,
                'rating' => 9.1,
                'market_value' => 15000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 28,
                'clean_sheets' => 0,
            ],
            [
                'id' => 12,
                'name' => 'Raphinha',
                'position' => 'Sturm',
                'age' => 27,
                'shirt_number' => 11,
                'goals' => 12,
                'assists' => 14,
                'rating' => 8.2,
                'market_value' => 65000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2027-06-30',
                'games_played' => 27,
                'clean_sheets' => 0,
            ],
            [
                'id' => 13,
                'name' => 'Ferran Torres',
                'position' => 'Sturm',
                'age' => 24,
                'shirt_number' => 7,
                'goals' => 7,
                'assists' => 5,
                'rating' => 7.4,
                'market_value' => 55000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2027-06-30',
                'games_played' => 23,
                'clean_sheets' => 0,
            ],
            [
                'id' => 14,
                'name' => 'Lamine Yamal',
                'position' => 'Sturm',
                'age' => 17,
                'shirt_number' => 27,
                'goals' => 9,
                'assists' => 8,
                'rating' => 8.0,
                'market_value' => 80000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 25,
                'clean_sheets' => 0,
            ],
        ];

        // Gruppiere Spieler nach Position (deutsche Reihenfolge)
        $positions = $this->groupPlayersByPosition($players);

        // Zähle verletzte Spieler
        $injuredCount = count(array_filter($players, fn($p) => $p['injured']));

        // Berechne Team-Statistiken
        $stats = $this->calculateTeamStats($players);

        return [
            'team' => [
                'name' => 'FC Barcelona',
                'players' => $players,
                'positions' => $positions,
                'injured_count' => $injuredCount,
                'stats' => $stats,
                'total_market_value' => $this->calculateTotalMarketValue($players),
            ]
        ];
    }

    private function groupPlayersByPosition(array $players): array
    {
        // Deutsche Positionen in logischer Reihenfolge: Torwart -> Abwehr -> Mittelfeld -> Sturm
        $positions = [
            'Torwart' => ['name' => 'Torwart', 'players' => []],
            'Abwehr' => ['name' => 'Abwehr', 'players' => []],
            'Mittelfeld' => ['name' => 'Mittelfeld', 'players' => []],
            'Sturm' => ['name' => 'Sturm', 'players' => []],
        ];

        foreach ($players as $player) {
            if (isset($positions[$player['position']])) {
                $positions[$player['position']]['players'][] = $player;
            }
        }

        // Sortiere Spieler innerhalb jeder Position nach Trikotnummer
        foreach ($positions as &$position) {
            usort($position['players'], fn($a, $b) => $a['shirt_number'] <=> $b['shirt_number']);
        }

        return array_values($positions);
    }

    private function calculateTeamStats(array $players): array
    {
        $totalGoals = array_sum(array_column($players, 'goals'));
        $totalAssists = array_sum(array_column($players, 'assists'));
        $averageAge = round(array_sum(array_column($players, 'age')) / count($players), 1);
        $averageRating = round(array_sum(array_column($players, 'rating')) / count($players), 1);

        return [
            'players_count' => count($players),
            'average_age' => $averageAge,
            'average_rating' => $averageRating,
            'total_goals' => $totalGoals,
            'total_assists' => $totalAssists,
            'wins' => 18, // Simuliert
            'draws' => 6, // Simuliert
            'losses' => 2, // Simuliert
        ];
    }

    private function calculateTotalMarketValue(array $players): int
    {
        return array_sum(array_column($players, 'market_value'));
    }
}