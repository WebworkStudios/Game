<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

#[Route(path: '/test/template-functions', methods: ['GET'], name: 'test.template.functions')]
readonly class TestTemplateFunctionsAction
{
    public function __construct(
        private Application $app
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        // Clear caches in debug mode
        if ($this->app->isDebug()) {
            $this->app->clearCaches();
        }

        // Get current locale from translator if available
        $currentLocale = 'de'; // Default fallback
        try {
            $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
            $currentLocale = $translator->getLocale();
        } catch (\Throwable) {
            // Translator not available, use default
        }

        return Response::view('pages/test/template-functions', [
            'current_locale' => $currentLocale,
            'test_data' => [
                'player' => 'Lionel Messi',
                'minute' => 90,
                'goals' => 5
            ],
            'performance_note' => 'Filter-only syntax is now optimized for better performance!'
        ]);
    }
}