<?php
// app/Actions/FilterDemoAction.php

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Filter Demo Action - Zeigt alle Must-Have Filter
 */
#[Route(path: '/test/filters', methods: ['GET'], name: 'test.filters')]
class FilterDemoAction
{

    public function __invoke(Request $request): Response
    {
        $data = [
            'app_name' => 'KickersCup Manager',

            // ===== EXISTING DATA =====
            'player_name' => 'lionel MESSI',
            'team_name' => 'FC Barcelona & Real Madrid United',
            'long_text' => 'This is a very long description of a football player that should be truncated to demonstrate the truncate filter functionality in our template system.',
            'empty_value' => '',
            'html_content' => '<strong>Bold text</strong> and <em>italic text</em>',
            'transfer_fee' => 222000000,
            'salary' => 50000000.50,
            'market_value' => 180000000,
            'goals' => 25,
            'match_date' => '2024-01-15 20:30:00',
            'invalid_date' => '',
            'players' => ['Messi', 'Ronaldo', 'Neymar', 'Mbappe', 'Haaland'],

            // ===== NEW DATA FOR ADVANCED FILTERS =====

            // Slug Filter Demo
            'article_title' => 'Messi\'s Amazing Goal: A Masterpiece!',
            'team_name_special' => 'Bayern München & FC Köln',

            // NL2BR Filter Demo
            'user_comment' => "Great match!\nFantastic performance.\nCan't wait for the next game!",
            'multiline_text' => "Line 1\nLine 2\nLine 3",

            // Strip Tags Filter Demo
            'rich_content' => '<p>This is <strong>bold</strong> and <em>italic</em> text with <a href="#">links</a>.</p>',
            'user_input' => '<script>alert("hack")</script>Safe content here',

            // JSON Filter Demo
            'player_stats' => [
                'name' => 'Lionel Messi',
                'goals' => 25,
                'assists' => 12,
                'position' => 'Forward'
            ],
            'simple_array' => ['apple', 'banana', 'orange'],

            // First/Last Filter Demo
            'navigation_items' => ['Home', 'Teams', 'Matches', 'League', 'Profile'],
            'alphabet' => ['A', 'B', 'C', 'D', 'E', 'F'],
            'single_item' => ['Only Item'],
            'empty_array' => [],
            'sample_string' => 'Hello World',
        ];

        return Response::view('pages/filter-demo', $data);
    }
}