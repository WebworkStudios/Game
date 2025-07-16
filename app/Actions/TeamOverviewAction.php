<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;

/**
 * Team Overview Action - Kaderübersicht und Spielerverwaltung
 *
 * Zeigt den kompletten Teamkader nach Positionen sortiert mit detaillierten
 * Spielerstatistiken, Marktwerten und Vertragsinformationen.
 * Implementiert ADR-Pattern mit sauberer Datenaufbereitung.
 */
#[Route(path: '/team', methods: ['GET'], name: 'team.overview')]
#[Route(path: '/team/overview', methods: ['GET'], name: 'team.overview.full')]
class TeamOverviewAction
{
    public function __construct(
        private readonly ResponseFactory $responseFactory
    ) {}

    public function __invoke(Request $request): Response
    {
        $teamData = $this->prepareTeamOverviewData();

        return $this->responseFactory->view('pages/team/overview', $teamData);
    }

    /**
     * Bereitet alle Team-Daten für die Kaderübersicht vor
     */
    private function prepareTeamOverviewData(): array
    {
        return [
            'team_info' => $this->getTeamInformation(),
            'squad_stats' => $this->getSquadStatistics(),
            'players_by_position' => $this->getPlayersGroupedByPosition(),
            'filter_options' => $this->getFilterOptions()
        ];
    }

    /**
     * Grundlegende Team-Informationen
     */
    private function getTeamInformation(): array
    {
        return [
            'name' => 'FC Barcelona',
            'league' => 'La Liga',
            'season' => '2023/24',
            'manager' => 'Xavi Hernández',
            'stadium' => 'Camp Nou',
            'squad_size' => 25,
            'average_age' => 26.3,
            'total_market_value' => 850000000,
            'formation' => '4-3-3'
        ];
    }

    /**
     * Kader-Statistiken im Überblick
     */
    private function getSquadStatistics(): array
    {
        return [
            'total_players' => 25,
            'goalkeepers' => 3,
            'defenders' => 8,
            'midfielders' => 8,
            'forwards' => 6,
            'injured_players' => 2,
            'players_over_30' => 7,
            'avg_market_value' => 34000000,
            'contract_expiring' => 4
        ];
    }

    /**
     * Spieler nach Positionen gruppiert mit vollständigen Daten
     */
    private function getPlayersGroupedByPosition(): array
    {
        return [
            'Torwart' => [
                [
                    'id' => 1,
                    'name' => 'Marc-André ter Stegen',
                    'age' => 31,
                    'shirt_number' => 1,
                    'nationality' => 'Deutschland',
                    'goals' => 0,
                    'assists' => 1,
                    'yellow_cards' => 2,
                    'red_cards' => 0,
                    'rating' => 8.5,
                    'market_value' => 30000000,
                    'injured' => false,
                    'injury_until' => null,
                    'contract_until' => '2028-06-30',
                    'games_played' => 28,
                    'clean_sheets' => 15,
                    'saves_per_game' => 3.2
                ],
                [
                    'id' => 13,
                    'name' => 'Iñaki Peña',
                    'age' => 24,
                    'shirt_number' => 13,
                    'nationality' => 'Spanien',
                    'goals' => 0,
                    'assists' => 0,
                    'yellow_cards' => 0,
                    'red_cards' => 0,
                    'rating' => 7.2,
                    'market_value' => 8000000,
                    'injured' => true,
                    'injury_until' => '2024-04-15',
                    'contract_until' => '2026-06-30',
                    'games_played' => 8,
                    'clean_sheets' => 3,
                    'saves_per_game' => 2.8
                ]
            ],

            'Abwehr' => [
                [
                    'id' => 4,
                    'name' => 'Ronald Araújo',
                    'age' => 25,
                    'shirt_number' => 4,
                    'nationality' => 'Uruguay',
                    'goals' => 3,
                    'assists' => 2,
                    'yellow_cards' => 8,
                    'red_cards' => 1,
                    'rating' => 8.1,
                    'market_value' => 70000000,
                    'injured' => false,
                    'injury_until' => null,
                    'contract_until' => '2026-06-30',
                    'games_played' => 25,
                    'tackles_per_game' => 2.4,
                    'aerial_duels_won' => 68
                ],
                [
                    'id' => 3,
                    'name' => 'Alejandro Balde',
                    'age' => 20,
                    'shirt_number' => 3,
                    'nationality' => 'Spanien',
                    'goals' => 1,
                    'assists' => 4,
                    'yellow_cards' => 3,
                    'red_cards' => 0,
                    'rating' => 7.8,
                    'market_value' => 40000000,
                    'injured' => false,
                    'injury_until' => null,
                    'contract_until' => '2028-06-30',
                    'games_played' => 22,
                    'tackles_per_game' => 1.9,
                    'aerial_duels_won' => 45
                ],
                [
                    'id' => 23,
                    'name' => 'Jules Koundé',
                    'age' => 25,
                    'shirt_number' => 23,
                    'nationality' => 'Frankreich',
                    'goals' => 2,
                    'assists' => 1,
                    'yellow_cards' => 5,
                    'red_cards' => 0,
                    'rating' => 7.9,
                    'market_value' => 60000000,
                    'injured' => false,
                    'injury_until' => null,
                    'contract_until' => '2027-06-30',
                    'games_played' => 27,
                    'tackles_per_game' => 2.1,
                    'aerial_duels_won' => 62
                ]
            ],

            'Mittelfeld' => [
                [
                    'id' => 21,
                    'name' => 'Frenkie de Jong',
                    'age' => 26,
                    'shirt_number' => 21,
                    'nationality' => 'Niederlande',
                    'goals' => 4,
                    'assists' => 6,
                    'yellow_cards' => 4,
                    'red_cards' => 0,
                    'rating' => 8.3,
                    'market_value' => 80000000,
                    'injured' => false,
                    'injury_until' => null,
                    'contract_until' => '2026-06-30',
                    'games_played' => 24,
                    'passes_per_game' => 89.2,
                    'pass_accuracy' => 92.1
                ],
                [
                    'id' => 8,
                    'name' => 'Pedri',
                    'age' => 21,
                    'shirt_number' => 8,
                    'nationality' => 'Spanien',
                    'goals' => 6,
                    'assists' => 8,
                    'yellow_cards' => 2,
                    'red_cards' => 0,
                    'rating' => 8.7,
                    'market_value' => 100000000,
                    'injured' => false,
                    'injury_until' => null,
                    'contract_until' => '2030-06-30',
                    'games_played' => 26,
                    'passes_per_game' => 76.4,
                    'pass_accuracy' => 91.8
                ],
                [
                    'id' => 30,
                    'name' => 'Gavi',
                    'age' => 19,
                    'shirt_number' => 30,
                    'nationality' => 'Spanien',
                    'goals' => 2,
                    'assists' => 3,
                    'yellow_cards' => 6,
                    'red_cards' => 0,
                    'rating' => 7.6,
                    'market_value' => 90000000,
                    'injured' => true,
                    'injury_until' => '2024-05-20',
                    'contract_until' => '2029-06-30',
                    'games_played' => 18,
                    'passes_per_game' => 65.3,
                    'pass_accuracy' => 88.9
                ]
            ],

            'Angriff' => [
                [
                    'id' => 9,
                    'name' => 'Robert Lewandowski',
                    'age' => 35,
                    'shirt_number' => 9,
                    'nationality' => 'Polen',
                    'goals' => 22,
                    'assists' => 4,
                    'yellow_cards' => 3,
                    'red_cards' => 0,
                    'rating' => 8.9,
                    'market_value' => 45000000,
                    'injured' => false,
                    'injury_until' => null,
                    'contract_until' => '2026-06-30',
                    'games_played' => 28,
                    'shots_per_game' => 4.2,
                    'shot_accuracy' => 62.1
                ],
                [
                    'id' => 7,
                    'name' => 'Ferran Torres',
                    'age' => 24,
                    'shirt_number' => 7,
                    'nationality' => 'Spanien',
                    'goals' => 8,
                    'assists' => 5,
                    'yellow_cards' => 1,
                    'red_cards' => 0,
                    'rating' => 7.4,
                    'market_value' => 35000000,
                    'injured' => false,
                    'injury_until' => null,
                    'contract_until' => '2027-06-30',
                    'games_played' => 23,
                    'shots_per_game' => 2.8,
                    'shot_accuracy' => 58.3
                ],
                [
                    'id' => 11,
                    'name' => 'Raphinha',
                    'age' => 27,
                    'shirt_number' => 11,
                    'nationality' => 'Brasilien',
                    'goals' => 10,
                    'assists' => 12,
                    'yellow_cards' => 4,
                    'red_cards' => 0,
                    'rating' => 8.2,
                    'market_value' => 60000000,
                    'injured' => false,
                    'injury_until' => null,
                    'contract_until' => '2027-06-30',
                    'games_played' => 27,
                    'shots_per_game' => 3.1,
                    'shot_accuracy' => 55.7
                ]
            ]
        ];
    }

    /**
     * Optionen für Kader-Filter
     */
    private function getFilterOptions(): array
    {
        return [
            'positions' => [
                'all' => 'Alle Positionen',
                'goalkeeper' => 'Torwart',
                'defender' => 'Abwehr',
                'midfielder' => 'Mittelfeld',
                'forward' => 'Angriff'
            ],
            'status' => [
                'all' => 'Alle Spieler',
                'available' => 'Verfügbar',
                'injured' => 'Verletzt',
                'suspended' => 'Gesperrt'
            ],
            'contract' => [
                'all' => 'Alle Verträge',
                'expiring' => 'Läuft aus',
                'long_term' => 'Langfristig'
            ]
        ];
    }
}