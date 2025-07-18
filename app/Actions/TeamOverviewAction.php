<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Assets\JavaScriptAssetManager;
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
 *
 * ERWEITERT: JavaScript Asset Manager Integration für Frontend-Funktionalität
 */
#[Route(path: '/team', methods: ['GET'], name: 'team.overview')]
#[Route(path: '/team/overview', methods: ['GET'], name: 'team.overview.full')]
class TeamOverviewAction
{
    public function __construct(
        private readonly ResponseFactory $responseFactory,
        private readonly JavaScriptAssetManager $assetManager
    ) {}

    public function __invoke(Request $request): Response
    {
        // JavaScript Assets für Team Overview registrieren
        $this->registerJavaScriptAssets();

        // Team-Daten vorbereiten
        $teamData = $this->prepareTeamOverviewData();

        return $this->responseFactory->view('pages/team/overview', $teamData);
    }

    /**
     * Registriert JavaScript Assets für die Team Overview Seite
     */
    private function registerJavaScriptAssets(): void
    {
        // Team Filter Funktionalität (externe Datei)
        $this->assetManager->addScript(
            'team/player-filters.js',
            ['defer' => true],
            50 // Hohe Priorität für Core-Funktionalität
        );

        // Debug/Development Tools (nur im Debug-Modus)
        if ($this->assetManager->debugMode) {
            $this->assetManager->addScript(
                'team/debug-tools.js',
                ['defer' => true],
                90 // Niedrige Priorität für Debug-Tools
            );
        }

        // Optional: Team Overview spezifische Utilities
        $this->assetManager->addScript(
            'team/team-overview.js',
            ['defer' => true],
            60
        );
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
            'financial_overview' => $this->getFinancialOverview(),
            // Neue: JavaScript-Konfiguration für Frontend
           'js_config' => $this->getJavaScriptConfiguration()
        ];
    }

    /**
     * JavaScript-Konfiguration für Frontend bereitstellen
     */
    private function getJavaScriptConfiguration(): array
    {
        return [
            'debug_mode' => $this->assetManager->debugMode,
            'filter_settings' => [
                'animation_duration' => 300,
                'enable_debug_alerts' => $this->assetManager->debugMode,
                'auto_reset_timeout' => 30000 // 30 Sekunden
            ],
            'player_data' => [
                'total_count' => 25,
                'positions' => ['goalkeepers', 'defenders', 'midfielders', 'forwards']
            ]
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
                    'market_value' => 25000000,
                    'contract_until' => '2025-06-30',
                    'injury_status' => null,
                    'goals' => 0,
                    'assists' => 2,
                    'yellow_cards' => 1,
                    'red_cards' => 0,
                    'minutes_played' => 2340,
                    'clean_sheets' => 12
                ],
                [
                    'id' => 13,
                    'name' => 'Iñaki Peña',
                    'age' => 24,
                    'nationality' => 'Spain',
                    'market_value' => 8000000,
                    'contract_until' => '2026-06-30',
                    'injury_status' => null,
                    'goals' => 0,
                    'assists' => 0,
                    'yellow_cards' => 0,
                    'red_cards' => 0,
                    'minutes_played' => 450,
                    'clean_sheets' => 3
                ],
                [
                    'id' => 26,
                    'name' => 'Ander Astralaga',
                    'age' => 20,
                    'nationality' => 'Spain',
                    'market_value' => 1000000,
                    'contract_until' => '2025-06-30',
                    'injury_status' => null,
                    'goals' => 0,
                    'assists' => 0,
                    'yellow_cards' => 0,
                    'red_cards' => 0,
                    'minutes_played' => 0,
                    'clean_sheets' => 0
                ]
            ],
            'defenders' => [
                [
                    'id' => 2,
                    'name' => 'João Cancelo',
                    'age' => 29,
                    'nationality' => 'Portugal',
                    'market_value' => 35000000,
                    'contract_until' => '2024-06-30',
                    'injury_status' => null,
                    'goals' => 2,
                    'assists' => 4,
                    'yellow_cards' => 3,
                    'red_cards' => 0,
                    'minutes_played' => 1890,
                    'clean_sheets' => 8
                ],
                [
                    'id' => 3,
                    'name' => 'Alejandro Balde',
                    'age' => 20,
                    'nationality' => 'Spain',
                    'market_value' => 50000000,
                    'contract_until' => '2028-06-30',
                    'injury_status' => null,
                    'goals' => 1,
                    'assists' => 3,
                    'yellow_cards' => 4,
                    'red_cards' => 0,
                    'minutes_played' => 2100,
                    'clean_sheets' => 9
                ],
                [
                    'id' => 4,
                    'name' => 'Ronald Araújo',
                    'age' => 24,
                    'nationality' => 'Uruguay',
                    'market_value' => 70000000,
                    'contract_until' => '2026-06-30',
                    'injury_status' => 'minor_injury',
                    'goals' => 3,
                    'assists' => 1,
                    'yellow_cards' => 5,
                    'red_cards' => 1,
                    'minutes_played' => 1650,
                    'clean_sheets' => 7
                ],
                [
                    'id' => 5,
                    'name' => 'Iñigo Martínez',
                    'age' => 32,
                    'nationality' => 'Spain',
                    'market_value' => 12000000,
                    'contract_until' => '2025-06-30',
                    'injury_status' => null,
                    'goals' => 2,
                    'assists' => 0,
                    'yellow_cards' => 2,
                    'red_cards' => 0,
                    'minutes_played' => 1800,
                    'clean_sheets' => 10
                ]
            ],
            'midfielders' => [
                [
                    'id' => 6,
                    'name' => 'Gavi',
                    'age' => 19,
                    'nationality' => 'Spain',
                    'market_value' => 90000000,
                    'contract_until' => '2026-06-30',
                    'injury_status' => 'long_term_injury',
                    'goals' => 2,
                    'assists' => 3,
                    'yellow_cards' => 6,
                    'red_cards' => 0,
                    'minutes_played' => 980,
                    'clean_sheets' => 0
                ],
                [
                    'id' => 8,
                    'name' => 'Pedri',
                    'age' => 21,
                    'nationality' => 'Spain',
                    'market_value' => 100000000,
                    'contract_until' => '2026-06-30',
                    'injury_status' => null,
                    'goals' => 4,
                    'assists' => 6,
                    'yellow_cards' => 3,
                    'red_cards' => 0,
                    'minutes_played' => 2250,
                    'clean_sheets' => 0
                ],
                [
                    'id' => 21,
                    'name' => 'Frenkie de Jong',
                    'age' => 26,
                    'nationality' => 'Netherlands',
                    'market_value' => 70000000,
                    'contract_until' => '2026-06-30',
                    'injury_status' => null,
                    'goals' => 3,
                    'assists' => 4,
                    'yellow_cards' => 4,
                    'red_cards' => 0,
                    'minutes_played' => 2010,
                    'clean_sheets' => 0
                ],
                [
                    'id' => 22,
                    'name' => 'Ilkay Gündogan',
                    'age' => 33,
                    'nationality' => 'Germany',
                    'market_value' => 25000000,
                    'contract_until' => '2025-06-30',
                    'injury_status' => null,
                    'goals' => 5,
                    'assists' => 7,
                    'yellow_cards' => 2,
                    'red_cards' => 0,
                    'minutes_played' => 1980,
                    'clean_sheets' => 0
                ]
            ],
            'forwards' => [
                [
                    'id' => 9,
                    'name' => 'Robert Lewandowski',
                    'age' => 35,
                    'nationality' => 'Poland',
                    'market_value' => 45000000,
                    'contract_until' => '2026-06-30',
                    'injury_status' => null,
                    'goals' => 22,
                    'assists' => 8,
                    'yellow_cards' => 3,
                    'red_cards' => 0,
                    'minutes_played' => 2340,
                    'clean_sheets' => 0
                ],
                [
                    'id' => 11,
                    'name' => 'Raphinha',
                    'age' => 27,
                    'nationality' => 'Brazil',
                    'market_value' => 60000000,
                    'contract_until' => '2027-06-30',
                    'injury_status' => null,
                    'goals' => 8,
                    'assists' => 6,
                    'yellow_cards' => 4,
                    'red_cards' => 0,
                    'minutes_played' => 1950,
                    'clean_sheets' => 0
                ],
                [
                    'id' => 7,
                    'name' => 'Ferran Torres',
                    'age' => 24,
                    'nationality' => 'Spain',
                    'market_value' => 35000000,
                    'contract_until' => '2027-06-30',
                    'injury_status' => null,
                    'goals' => 6,
                    'assists' => 3,
                    'yellow_cards' => 2,
                    'red_cards' => 0,
                    'minutes_played' => 1620,
                    'clean_sheets' => 0
                ]
            ]
        ];
    }

    /**
     * Filter-Optionen für Frontend
     */
    private function getFilterOptions(): array
    {
        return [
            'positions' => [
                ['value' => 'all', 'label' => 'Alle Positionen'],
                ['value' => 'goalkeepers', 'label' => 'Torhüter'],
                ['value' => 'defenders', 'label' => 'Verteidiger'],
                ['value' => 'midfielders', 'label' => 'Mittelfeld'],
                ['value' => 'forwards', 'label' => 'Stürmer']
            ],
            'status' => [
                ['value' => 'all', 'label' => 'Alle Spieler'],
                ['value' => 'available', 'label' => 'Verfügbar'],
                ['value' => 'injured', 'label' => 'Verletzt'],
                ['value' => 'suspended', 'label' => 'Gesperrt']
            ],
            'age_groups' => [
                ['value' => 'all', 'label' => 'Alle Altersgruppen'],
                ['value' => 'young', 'label' => 'U21 (unter 21)'],
                ['value' => 'prime', 'label' => 'Prime (21-30)'],
                ['value' => 'veteran', 'label' => 'Veteran (über 30)']
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
            'total_salaries' => 180000000,
            'transfer_budget' => 150000000,
            'wage_budget_remaining' => 45000000,
            'most_valuable_player' => [
                'name' => 'Pedri',
                'value' => 100000000
            ],
            'contracts_expiring' => 4,
            'contract_renewal_priority' => [
                'high' => 2,
                'medium' => 3,
                'low' => 1
            ]
        ];
    }
}