<?php


declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Template Engine Demo Action - Zeigt alle Template-Features
 */
#[Route(path: '/test/templates', methods: ['GET'], name: 'test.templates')]
class TemplateTestAction
{
    public function __invoke(Request $request): Response
    {
        // Demo-Daten für Template-Features
        $data = [
            'user' => [
                'name' => 'John Doe',
                'is_admin' => true,
                'email' => 'john@example.com',
            ],
            'demo' => [
                'message' => 'Hello from Template Engine!',
                'number' => 42,
                'show_secret' => true,
                'items' => [
                    [
                        'name' => 'First Item',
                        'active' => true,
                        'description' => 'This is the first demo item'
                    ],
                    [
                        'name' => 'Second Item',
                        'active' => false,
                        'description' => 'This item is currently inactive'
                    ],
                    [
                        'name' => 'Third Item',
                        'active' => true,
                        'description' => 'Another active item'
                    ],
                ],
                'numbers' => [1, 2, 3, 4, 5],
                'nested' => [
                    'level1' => 'First level value',
                    'level2' => [
                        'value' => 'Second level value',
                        'deep' => [
                            'message' => 'Deep nested message!'
                        ]
                    ]
                ],
                'sample_player' => [
                    'name' => 'Lionel Messi',
                    'position' => 'Forward',
                    'age' => 36,
                    'goals' => 25,
                    'assists' => 12,
                    'rating' => 9.2,
                    'injured' => false,
                    'injury_until' => null,
                ],
                'teams' => [
                    [
                        'name' => 'Barcelona FC',
                        'players' => [
                            ['name' => 'Pedri', 'position' => 'Midfielder'],
                            ['name' => 'Gavi', 'position' => 'Midfielder'],
                            ['name' => 'Lewandowski', 'position' => 'Forward'],
                        ]
                    ],
                    [
                        'name' => 'Real Madrid',
                        'players' => [
                            ['name' => 'Vinicius Jr', 'position' => 'Winger'],
                            ['name' => 'Benzema', 'position' => 'Forward'],
                            ['name' => 'Modric', 'position' => 'Midfielder'],
                        ]
                    ],
                    [
                        'name' => 'Empty Team',
                        'players' => []
                    ]
                ]
            ]
        ];

        // Template rendern
        return Response::view('pages/test/templates', $data);
    }
}