<?php
// app/Actions/HomeAction.php - Aktiviere das Template-Rendering

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

#[Route(path: '/', methods: ['GET'], name: 'home')]
class HomeAction
{
    public function __invoke(Request $request): Response
    {
        $data = [
            'app_name' => 'KickersCup Manager',
            'app_version' => '1.0.0',
            'welcome_message' => 'Welcome to your Football Manager!',
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'isAdmin' => true,
                'team' => [  // ← Jetzt vollständig
                    'name' => 'FC Barcelona',
                    'league' => 'La Liga'
                ]
            ],
            'features' => [
                [
                    'icon' => '⚡',
                    'title' => 'Performance',
                    'description' => 'Route-Caching und Lazy Loading',
                    'active' => true
                ],
                [
                    'icon' => '🎯',
                    'title' => 'Modern',
                    'description' => 'PHP 8.4 Features',
                    'active' => true
                ],
                [
                    'icon' => '🎨',
                    'title' => 'Template Engine',
                    'description' => 'Inheritance, Loops, Conditionals',
                    'active' => true
                ]
            ]
        ];

        return Response::view('pages/home', $data);
    }
}