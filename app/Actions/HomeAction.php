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
        error_log("HomeAction called!");

        $data = [
            'app_name' => 'KickersCup Manager',
            'app_version' => '1.0.0',
            'welcome_message' => 'Welcome to your Football Manager!',
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'isAdmin' => true,
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
                ]
            ]
        ];

        try {
            $engine = \Framework\Core\ServiceRegistry::get(\Framework\Templating\TemplateEngine::class);
            error_log("Template Engine gefunden: " . get_class($engine));

            // Let's check template paths
            $paths = $engine->getPaths();
            error_log("Template Pfade: " . json_encode($paths));

            // Test the simple template first
            $content = $engine->render('pages/home', $data);
            error_log("Template gerendert, Länge: " . strlen($content));
            error_log("Template Anfang: " . substr($content, 0, 200));

            $response = Response::view('pages/home', $data);
            return $response;

        } catch (\Throwable $e) {
            error_log("Fehler in HomeAction: " . $e->getMessage());
            error_log("Stack: " . $e->getTraceAsString());

            return Response::ok("
            <!DOCTYPE html>
            <html>
            <head>
                <title>DEBUG: Template Error</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 40px; }
                    .error { background: #ffe6e6; border: 1px solid #ff0000; padding: 20px; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class='error'>
                    <h1>🚨 DEBUG: Template Error</h1>
                    <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                    <p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>
                </div>
            </body>
            </html>
        ");
        }
    }
}