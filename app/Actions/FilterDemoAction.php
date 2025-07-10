<?php

// app/Actions/FilterDemoAction.php

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Filter Demo Action - Zeigt erweiterte Filter-Features
 */
#[Route(path: '/test/filters', methods: ['GET'], name: 'test.filters')]
class FilterDemoAction
{
    public function __invoke(Request $request): Response
    {
        $data = [
            'demo' => [
                // Text data
                'long_text' => 'This is a very long text that should be truncated to demonstrate the truncate filter functionality in our templating system.',
                'player_name' => 'lionel messi',
                'team_name' => 'FC Barcelona & Real Madrid',

                // Number data
                'transfer_fee' => 222000000,
                'salary' => 50000000.50,
                'goals' => 25,
                'market_value' => 180000000,

                // Date data
                'match_date' => '2024-01-15 20:30:00',
                'birth_date' => '1987-06-24',

                // Rating data
                'player_rating' => 9.2,
                'team_rating' => 4.5,

                // JSON data
                'player_data' => [
                    'name' => 'Lionel Messi',
                    'position' => 'Forward',
                    'goals' => 25,
                    'assists' => 12
                ],

                // Default/empty values
                'comment' => 'Great player with excellent skills!',
                'empty_bio' => '',
                'empty_value' => null,

                // HTML content
                'html_content' => '<strong>Bold text</strong> & <em>italic text</em>',

                // Plural tests
                'player_count' => 1,
            ]
        ];

        return Response::view('pages/test/filters', $data);
    }
}