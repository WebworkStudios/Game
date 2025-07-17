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
            'filter_options' => $this->getFilterOptions(),
            'financial_overview' => $this->getFinancialOverview()
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
            'season' => '2024/25',
            'manager' => 'Xavi Hernández',
            'stadium' => 'Camp Nou',
            'squad_size' => 25,
            'average_age' => 26.3,
            'total_market_value' => 850000000,
            'formation' => '4-3-3',
            'logo_url' => '/assets/images/teams/barcelona-logo.png'
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
            'contract_expiring' => 4,
            'foreign_players' => 18,
            'youth_players' => 3
        ];
    }

    /**
     * Spieler nach Positionen gruppiert
     */
    private function getPlayersGroupedByPosition(): array
    {
        return [
            'goalkeepers' => [
                [
                    'id' => 1,
                    'name' => 'Marc-André ter Stegen',
                    'age' => 31,
                    'nationality' => 'Germany',
                    'market_value' => 35000000,
                    'contract_until' => '2025-06-30',
                    'shirt_number' => 1,
                    'injured' => false,
                    'goals' => 0,
                    'assists' => 2,
                    'rating' => 8.5,
                    'appearances' => 25
                ],
                [
                    'id' => 13,
                    'name' => 'Iñaki Peña',
                    'age' => 24,
                    'nationality' => 'Spain',
                    'market_value' => 8000000,
                    'contract_until' => '2026-06-30',
                    'shirt_number' => 13,
                    'injured' => false,
                    'goals' => 0,
                    'assists' => 0,
                    'rating' => 7.2,
                    'appearances' => 8
                ],
                [
                    'id' => 26,
                    'name' => 'Ander Astralaga',
                    'age' => 20,
                    'nationality' => 'Spain',
                    'market_value' => 500000,
                    'contract_until' => '2025-06-30',
                    'shirt_number' => 26,
                    'injured' => false,
                    'goals' => 0,
                    'assists' => 0,
                    'rating' => 6.8,
                    'appearances' => 2
                ]
            ],
            'defenders' => [
                [
                    'id' => 2,
                    'name' => 'João Cancelo',
                    'age' => 29,
                    'nationality' => 'Portugal',
                    'market_value' => 40000000,
                    'contract_until' => '2025-06-30',
                    'shirt_number' => 2,
                    'injured' => false,
                    'goals' => 3,
                    'assists' => 8,
                    'rating' => 8.1,
                    'appearances' => 28
                ],
                [
                    'id' => 3,
                    'name' => 'Alejandro Balde',
                    'age' => 20,
                    'nationality' => 'Spain',
                    'market_value' => 45000000,
                    'contract_until' => '2028-06-30',
                    'shirt_number' => 3,
                    'injured' => true,
                    'injury_until' => '2025-02-15',
                    'goals' => 1,
                    'assists' => 5,
                    'rating' => 7.8,
                    'appearances' => 22
                ],
                [
                    'id' => 4,
                    'name' => 'Ronald Araújo',
                    'age' => 24,
                    'nationality' => 'Uruguay',
                    'market_value' => 60000000,
                    'contract_until' => '2026-06-30',
                    'shirt_number' => 4,
                    'injured' => false,
                    'goals' => 2,
                    'assists' => 1,
                    'rating' => 8.3,
                    'appearances' => 30
                ],
                [
                    'id' => 5,
                    'name' => 'Iñigo Martínez',
                    'age' => 32,
                    'nationality' => 'Spain',
                    'market_value' => 15000000,
                    'contract_until' => '2025-06-30',
                    'shirt_number' => 5,
                    'injured' => false,
                    'goals' => 1,
                    'assists' => 2,
                    'rating' => 7.9,
                    'appearances' => 24
                ]
            ],
            'midfielders' => [
                [
                    'id' => 8,
                    'name' => 'Pedri',
                    'age' => 21,
                    'nationality' => 'Spain',
                    'market_value' => 100000000,
                    'contract_until' => '2026-06-30',
                    'shirt_number' => 8,
                    'injured' => false,
                    'goals' => 6,
                    'assists' => 9,
                    'rating' => 8.7,
                    'appearances' => 32
                ],
                [
                    'id' => 6,
                    'name' => 'Gavi',
                    'age' => 19,
                    'nationality' => 'Spain',
                    'market_value' => 90000000,
                    'contract_until' => '2026-06-30',
                    'shirt_number' => 6,
                    'injured' => true,
                    'injury_until' => '2025-03-01',
                    'goals' => 2,
                    'assists' => 4,
                    'rating' => 8.2,
                    'appearances' => 18
                ],
                [
                    'id' => 21,
                    'name' => 'Frenkie de Jong',
                    'age' => 26,
                    'nationality' => 'Netherlands',
                    'market_value' => 70000000,
                    'contract_until' => '2026-06-30',
                    'shirt_number' => 21,
                    'injured' => false,
                    'goals' => 4,
                    'assists' => 6,
                    'rating' => 8.0,
                    'appearances' => 29
                ],
                [
                    'id' => 22,
                    'name' => 'İlkay Gündoğan',
                    'age' => 33,
                    'nationality' => 'Germany',
                    'market_value' => 25000000,
                    'contract_until' => '2025-06-30',
                    'shirt_number' => 22,
                    'injured' => false,
                    'goals' => 8,
                    'assists' => 11,
                    'rating' => 8.4,
                    'appearances' => 31
                ]
            ],
            'forwards' => [
                [
                    'id' => 9,
                    'name' => 'Robert Lewandowski',
                    'age' => 35,
                    'nationality' => 'Poland',
                    'market_value' => 45000000,
                    'contract_until' => '2025-06-30',
                    'shirt_number' => 9,
                    'injured' => false,
                    'goals' => 24,
                    'assists' => 8,
                    'rating' => 9.1,
                    'appearances' => 33
                ],
                [
                    'id' => 11,
                    'name' => 'Raphinha',
                    'age' => 27,
                    'nationality' => 'Brazil',
                    'market_value' => 60000000,
                    'contract_until' => '2027-06-30',
                    'shirt_number' => 11,
                    'injured' => false,
                    'goals' => 12,
                    'assists' => 15,
                    'rating' => 8.6,
                    'appearances' => 35
                ],
                [
                    'id' => 7,
                    'name' => 'Ferran Torres',
                    'age' => 24,
                    'nationality' => 'Spain',
                    'market_value' => 40000000,
                    'contract_until' => '2027-06-30',
                    'shirt_number' => 7,
                    'injured' => false,
                    'goals' => 9,
                    'assists' => 6,
                    'rating' => 7.8,
                    'appearances' => 28
                ],
                [
                    'id' => 14,
                    'name' => 'João Félix',
                    'age' => 24,
                    'nationality' => 'Portugal',
                    'market_value' => 50000000,
                    'contract_until' => '2024-06-30',
                    'shirt_number' => 14,
                    'injured' => false,
                    'goals' => 7,
                    'assists' => 4,
                    'rating' => 7.9,
                    'appearances' => 26
                ]
            ]
        ];
    }

    /**
     * Filter-Optionen für die Spielersuche
     */
    private function getFilterOptions(): array
    {
        return [
            'positions' => [
                'all' => 'Alle Positionen',
                'goalkeeper' => 'Torhüter',
                'defender' => 'Verteidiger',
                'midfielder' => 'Mittelfeld',
                'forward' => 'Stürmer'
            ],
            'status' => [
                'all' => 'Alle Spieler',
                'available' => 'Verfügbar',
                'injured' => 'Verletzt'
            ],
            'age_groups' => [
                'all' => 'Alle Altersgruppen',
                'young' => 'U21 (unter 21)',
                'prime' => 'Prime (21-30)',
                'veteran' => 'Veteran (über 30)'
            ]
        ];
    }

    /**
     * Finanzielle Übersicht des Teams
     */
    private function getFinancialOverview(): array
    {
        return [
            'total_market_value' => 850000000,
            'total_market_value_millions' => 850.0,
            'avg_market_value' => 34000000,
            'most_valuable_player' => [
                'name' => 'Pedri',
                'value' => 100000000
            ],
            'salary_budget' => 400000000,
            'salary_spent' => 380000000,
            'salary_remaining' => 20000000,
            'transfer_budget' => 50000000
        ];
    }
}