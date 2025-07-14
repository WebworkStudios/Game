<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;

#[Route(path: '/', methods: ['GET'], name: 'home')]
class HomeAction
{
    public function __construct(
        private readonly ResponseFactory $responseFactory
    )
    {
    }

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
                'team' => [
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

        return $this->responseFactory->view('pages/home', $data);
    }
}