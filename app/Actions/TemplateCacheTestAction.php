<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

#[Route(path: '/test/template-cache', methods: ['GET', 'POST'], name: 'test.template.cache')]
class TemplateCacheTestAction
{
    public function __construct(
        private readonly Application $app
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        $engine = $this->app->getTemplateEngine();
        $cache = $engine->getCache();

        // Handle cache operations
        if ($request->isPost()) {
            $action = $request->input('action');

            switch ($action) {
                case 'clear':
                    $cleared = $cache->clearAll();
                    $message = "Cleared {$cleared} cached templates";
                    break;

                case 'warmup':
                    $warmed = $this->warmupCache($engine);
                    $message = "Warmed up {$warmed} templates";
                    break;

                default:
                    $message = "Unknown action";
            }

            return Response::json(['success' => true, 'message' => $message]);
        }

        // Show cache statistics
        $stats = $cache->getStats();
        $engineStats = $engine->getCacheStats();

        return Response::view('pages/test/template-cache', [
            'cache_stats' => $stats,
            'engine_stats' => $engineStats,
            'templates_tested' => $this->getTestTemplates(),
        ]);
    }

    private function warmupCache($engine): int
    {
        $templates = [
            'pages/home',
            'pages/test/localization',
            'pages/test/template-functions',
            'layouts/base',
        ];

        $warmed = 0;
        foreach ($templates as $template) {
            try {
                $engine->render($template, ['app_name' => 'Test']);
                $warmed++;
            } catch (\Throwable $e) {
                error_log("Warmup failed for $template: " . $e->getMessage());
            }
        }

        return $warmed;
    }

    private function getTestTemplates(): array
    {
        return [
            'pages/home',
            'pages/test/localization',
            'pages/test/template-functions',
            'layouts/base',
        ];
    }
}