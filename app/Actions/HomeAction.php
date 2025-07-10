<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Home-Action mit Template
 */
#[Route(path: '/', methods: ['GET'], name: 'home')]
#[Route(path: '/welcome', methods: ['GET'], name: 'welcome')]
class HomeAction
{
    public function __invoke(Request $request): Response
    {
        $data = [
            'welcome_message' => 'Welcome to your Football Manager!',
            'features' => [
                [
                    'icon' => '⚡',
                    'title' => 'Performance',
                    'description' => 'Route-Caching und Lazy Loading für maximale Geschwindigkeit'
                ],
                [
                    'icon' => '🎯',
                    'title' => 'Modern',
                    'description' => 'PHP 8.4 Features, Attributes und strikte Typisierung'
                ],
                [
                    'icon' => '🔧',
                    'title' => 'Flexible',
                    'description' => 'Dependency Injection und Middleware-Support'
                ],
                [
                    'icon' => '🎨',
                    'title' => 'Template Engine',
                    'description' => 'Eigene Template-Engine mit Caching und Vererbung'
                ]
            ],
            'quick_links' => [
                ['url' => '/team', 'text' => 'Team Overview'],
                ['url' => '/test/templates', 'text' => 'Template Demo'],
                ['url' => '/users/123', 'text' => 'User Profile'],
                ['url' => '/api/users/123', 'text' => 'API Demo'],
            ]
        ];

        return Response::view('pages/home', $data);
    }
}