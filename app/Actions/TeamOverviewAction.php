<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;

/**
 * Team Overview Action - Kaderübersicht von Torwart bis Sturm
 */
#[Route(path: '/team', methods: ['GET'], name: 'team.overview')]
#[Route(path: '/team/overview', methods: ['GET'], name: 'team.overview.full')]
class TeamOverviewAction
{
    public function __construct(
        private readonly ResponseFactory $responseFactory
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        $teamData = $this->getTeamData();

        return $this->responseFactory->view('pages/team/overview', $teamData);
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
                'yellow_cards' => 8,
                'red_cards' => 1,
            ],
            [
                'id' => 4,
                'name' => 'Jules Koundé',
                'position' => 'Abwehr',
                'age' => 25,
                'shirt_number' => 23,
                'goals' => 1,
                'assists' => 3,
                'rating' => 7.8,
                'market_value' => 55000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2027-06-30',
                'games_played' => 30,
                'yellow_cards' => 5,
                'red_cards' => 0,
            ],
            [
                'id' => 5,
                'name' => 'Andreas Christensen',
                'position' => 'Abwehr',
                'age' => 28,
                'shirt_number' => 15,
                'goals' => 2,
                'assists' => 1,
                'rating' => 7.6,
                'market_value' => 35000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 22,
                'yellow_cards' => 3,
                'red_cards' => 0,
            ],
            [
                'id' => 6,
                'name' => 'Alejandro Balde',
                'position' => 'Abwehr',
                'age' => 20,
                'shirt_number' => 3,
                'goals' => 1,
                'assists' => 4,
                'rating' => 7.4,
                'market_value' => 50000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2028-06-30',
                'games_played' => 27,
                'yellow_cards' => 6,
                'red_cards' => 0,
            ],

            // Mittelfeld
            [
                'id' => 7,
                'name' => 'Pedri',
                'position' => 'Mittelfeld',
                'age' => 21,
                'shirt_number' => 8,
                'goals' => 4,
                'assists' => 8,
                'rating' => 8.3,
                'market_value' => 100000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 29,
                'yellow_cards' => 4,
                'red_cards' => 0,
            ],
            [
                'id' => 8,
                'name' => 'Gavi',
                'position' => 'Mittelfeld',
                'age' => 19,
                'shirt_number' => 6,
                'goals' => 2,
                'assists' => 5,
                'rating' => 7.9,
                'market_value' => 90000000,
                'injured' => true,
                'injury_until' => '2024-09-15',
                'contract_until' => '2026-06-30',
                'games_played' => 15,
                'yellow_cards' => 7,
                'red_cards' => 0,
            ],
            [
                'id' => 9,
                'name' => 'Frenkie de Jong',
                'position' => 'Mittelfeld',
                'age' => 26,
                'shirt_number' => 21,
                'goals' => 3,
                'assists' => 6,
                'rating' => 7.7,
                'market_value' => 70000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 24,
                'yellow_cards' => 5,
                'red_cards' => 0,
            ],
            [
                'id' => 10,
                'name' => 'Ilkay Gündogan',
                'position' => 'Mittelfeld',
                'age' => 33,
                'shirt_number' => 22,
                'goals' => 5,
                'assists' => 14,
                'rating' => 8.0,
                'market_value' => 25000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2025-06-30',
                'games_played' => 31,
                'yellow_cards' => 3,
                'red_cards' => 0,
            ],

            // Angriff
            [
                'id' => 11,
                'name' => 'Robert Lewandowski',
                'position' => 'Sturm',
                'age' => 35,
                'shirt_number' => 9,
                'goals' => 22,
                'assists' => 8,
                'rating' => 8.6,
                'market_value' => 15000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 30,
                'yellow_cards' => 2,
                'red_cards' => 0,
            ],
            [
                'id' => 12,
                'name' => 'Raphinha',
                'position' => 'Sturm',
                'age' => 27,
                'shirt_number' => 11,
                'goals' => 8,
                'assists' => 12,
                'rating' => 7.8,
                'market_value' => 60000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2027-06-30',
                'games_played' => 32,
                'yellow_cards' => 4,
                'red_cards' => 0,
            ],
            [
                'id' => 13,
                'name' => 'Ferran Torres',
                'position' => 'Sturm',
                'age' => 24,
                'shirt_number' => 7,
                'goals' => 6,
                'assists' => 4,
                'rating' => 7.3,
                'market_value' => 40000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2027-06-30',
                'games_played' => 26,
                'yellow_cards' => 2,
                'red_cards' => 0,
            ],
            [
                'id' => 14,
                'name' => 'Lamine Yamal',
                'position' => 'Sturm',
                'age' => 16,
                'shirt_number' => 27,
                'goals' => 5,
                'assists' => 7,
                'rating' => 7.5,
                'market_value' => 15000000,
                'injured' => false,
                'injury_until' => null,
                'contract_until' => '2026-06-30',
                'games_played' => 20,
                'yellow_cards' => 1,
                'red_cards' => 0,
            ],
        ];

        // Gruppiere Spieler nach Position
        $positions = [
            'Torwart' => [],
            'Abwehr' => [],
            'Mittelfeld' => [],
            'Sturm' => []
        ];

        foreach ($players as $player) {
            $positions[$player['position']][] = $player;
        }

        // Team-Statistiken
        $teamStats = [
            'total_players' => count($players),
            'injured_players' => count(array_filter($players, fn($p) => $p['injured'])),
            'total_goals' => array_sum(array_column($players, 'goals')),
            'total_assists' => array_sum(array_column($players, 'assists')),
            'total_market_value' => array_sum(array_column($players, 'market_value')),
            'average_age' => round(array_sum(array_column($players, 'age')) / count($players), 1),
            'total_games' => array_sum(array_column($players, 'games_played')),
            'top_scorer' => $this->getTopScorer($players),
            'most_assists' => $this->getMostAssists($players),
        ];

        return [
            'app_name' => 'KickersCup Manager',
            'team' => [
                'name' => 'FC Barcelona',
                'season' => '2023/24',
                'formation' => '4-3-3',
                'positions' => array_map(function ($position, $name) {
                    return [
                        'name' => $name,
                        'players' => array_map(function ($player) {
                            $player['market_value_millions'] = $player['market_value'] / 1000000;
                            return $player;
                        }, $position)
                    ];
                }, $positions, array_keys($positions)),
                'stats' => [
                    'players_count' => $teamStats['total_players'],
                    'average_age' => $teamStats['average_age'],
                    'total_goals' => $teamStats['total_goals'],
                    'total_assists' => $teamStats['total_assists'],
                    'wins' => 12, // Mock data
                    'draws' => 8,
                    'losses' => 3,
                    'average_rating' => 7.8,
                ],
                'injured_count' => $teamStats['injured_players'],
                'total_market_value_millions' => $teamStats['total_market_value'] / 1000000,
            ],
            'next_match' => [
                'opponent' => 'Real Madrid',
                'date' => '2024-02-20',
                'time' => '21:00',
                'venue' => 'Camp Nou'
            ]
        ];
    }

    private function getTopScorer(array $players): array
    {
        $topScorer = null;
        $maxGoals = 0;

        foreach ($players as $player) {
            if ($player['goals'] > $maxGoals) {
                $maxGoals = $player['goals'];
                $topScorer = $player;
            }
        }

        return $topScorer ?? [];
    }

    private function getMostAssists(array $players): array
    {
        $topAssist = null;
        $maxAssists = 0;

        foreach ($players as $player) {
            if ($player['assists'] > $maxAssists) {
                $maxAssists = $player['assists'];
                $topAssist = $player;
            }
        }

        return $topAssist ?? [];
    }
}