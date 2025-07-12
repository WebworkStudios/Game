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

                case 'clear_tag':
                    $tag = $request->input('tag', '');
                    if (!empty($tag)) {
                        $cleared = $cache->invalidateByTag($tag);
                        $message = "Cleared {$cleared} templates with tag '{$tag}'";
                    } else {
                        $message = "No tag specified";
                    }
                    break;

                case 'test_fragment':
                    $result = $this->testFragmentCaching($cache);
                    $message = $result['message'];
                    break;

                case 'test_tags':
                    $result = $this->testTaggedCaching($cache);
                    $message = $result['message'];
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
            'test_tags' => $this->getTestTags(),
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

    private function testFragmentCaching($cache): array
    {
        $testContent = '<div>Fragment Test Content - ' . time() . '</div>';
        $key = 'test_fragment_' . time();

        // Store fragment with 60 second TTL
        $stored = $cache->storeFragment($key, $testContent, 60, ['test', 'fragment']);

        if ($stored) {
            // Try to retrieve it
            $retrieved = $cache->getFragment($key);
            if ($retrieved === $testContent) {
                return ['success' => true, 'message' => 'Fragment caching test passed'];
            } else {
                return ['success' => false, 'message' => 'Fragment retrieval failed'];
            }
        }

        return ['success' => false, 'message' => 'Fragment storage failed'];
    }

    private function testTaggedCaching($cache): array
    {
        $testData = [
            ['key' => 'player_1', 'content' => 'Player 1 Data', 'tags' => ['player', 'team_1']],
            ['key' => 'player_2', 'content' => 'Player 2 Data', 'tags' => ['player', 'team_2']],
            ['key' => 'team_stats', 'content' => 'Team Stats', 'tags' => ['team', 'stats']],
        ];

        // Store test fragments
        foreach ($testData as $item) {
            $cache->storeFragment($item['key'], $item['content'], 300, $item['tags']);
        }

        // Test tag invalidation
        $cleared = $cache->invalidateByTag('player');

        // Check if player fragments were cleared but team_stats remains
        $player1 = $cache->getFragment('player_1');
        $player2 = $cache->getFragment('player_2');
        $teamStats = $cache->getFragment('team_stats');

        if ($player1 === null && $player2 === null && $teamStats !== null) {
            return ['success' => true, 'message' => "Tag invalidation test passed - cleared {$cleared} items"];
        } else {
            return ['success' => false, 'message' => 'Tag invalidation test failed'];
        }
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

    private function getTestTags(): array
    {
        return [
            'test' => 'Test fragments',
            'player' => 'Player-related cache',
            'team' => 'Team-related cache',
            'match' => 'Match-related cache',
            'stats' => 'Statistics cache',
            'live' => 'Live data cache',
        ];
    }
}