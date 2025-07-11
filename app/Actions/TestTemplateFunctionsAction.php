<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

#[Route(path: '/test/template-functions', methods: ['GET'], name: 'test.template.functions')]
class TestTemplateFunctionsAction
{
    public function __construct(
        private readonly Application $app
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        // Clear caches in debug mode
        if ($this->app->isDebug()) {
            $this->app->clearCaches();
        }

        return Response::view('pages/test/template-functions', [
            'test_data' => [
                'player' => 'Lionel Messi',
                'minute' => 90,
                'goals' => 5
            ]
        ]);
    }
}