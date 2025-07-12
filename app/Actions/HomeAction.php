<?php
// app/Actions/HomeAction.php

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Home-Action mit Template-Demo
 */
#[Route(path: '/', methods: ['GET'], name: 'home')]
#[Route(path: '/welcome', methods: ['GET'], name: 'welcome')]
class HomeAction
{
    public function __invoke(Request $request): Response
    {
        $data = [
            'app_name' => 'KickersCup Manager',
            'app_version' => '1.0.0',
            'welcome_message' => 'Welcome to your Football Manager!',

            // User-Daten für Variablen-Demo
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'isAdmin' => true,
                'team' => [
                    'name' => 'FC Barcelona',
                    'league' => 'La Liga'
                ]
            ],

            // Features für Loop-Demo
            'features' => [
                [
                    'icon' => '⚡',
                    'title' => 'Performance',
                    'description' => 'Route-Caching und Lazy Loading für maximale Geschwindigkeit',
                    'active' => true
                ],
                [
                    'icon' => '🎯',
                    'title' => 'Modern',
                    'description' => 'PHP 8.4 Features, Attributes und strikte Typisierung',
                    'active' => true
                ],
                [
                    'icon' => '🔧',
                    'title' => 'Flexible',
                    'description' => 'Dependency Injection und Middleware-Support',
                    'active' => false
                ],
                [
                    'icon' => '🎨',
                    'title' => 'Template Engine',
                    'description' => 'Eigene Template-Engine mit Vererbung und Komponenten',
                    'active' => true
                ]
            ],

            // Navigation für weitere Demos
            'quick_links' => [
                ['url' => '/team', 'text' => 'Team Overview'],
                ['url' => '/test/templates', 'text' => 'Template Demo'],
                ['url' => '/users/123', 'text' => 'User Profile'],
                ['url' => '/api/users/123', 'text' => 'API Demo'],
            ],

            // Statistiken für Conditional-Demo
            'stats' => [
                'total_players' => 25,
                'active_matches' => 3,
                'next_match' => '2024-01-20 15:30:00'
            ]
        ];

        return Response::view('pages/home', $data);
    }
}