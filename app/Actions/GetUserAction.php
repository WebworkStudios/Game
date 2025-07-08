<?php


declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Beispiel-Action für User-Anzeige
 */
#[Route(
    path: '/users/{id}',
    methods: ['GET'],
    name: 'user.show',
    constraints: ['id' => '\d+']
)]
#[Route(
    path: '/api/users/{id}',
    methods: ['GET'],
    name: 'api.user.show',
    constraints: ['id' => '\d+']
)]
class GetUserAction
{
    public function __invoke(Request $request): Response
    {
        $userId = $request->input('id');

        // Simuliere User-Daten
        $user = [
            'id' => (int)$userId,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => '2024-01-15T10:30:00Z',
        ];

        // JSON für API-Endpunkt
        if (str_starts_with($request->getPath(), '/api/')) {
            return Response::json($user);
        }

        // HTML für Web-Endpunkt
        $html = $this->renderUserHtml($user);
        return Response::ok($html);
    }

    private function renderUserHtml(array $user): string
    {
        return "
        <!DOCTYPE html>
        <html lang=de>
        <head>
            <title>User Profile - {$user['name']}</title>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .user-card { 
                    border: 1px solid #ddd; 
                    border-radius: 8px; 
                    padding: 20px; 
                    max-width: 400px; 
                }
                .user-id { color: #666; font-size: 0.9em; }
                .user-name { margin: 10px 0; font-size: 1.5em; }
                .user-email { color: #0066cc; }
            </style>
        </head>
        <body>
            <div class='user-card'>
                <div class='user-id'>User ID: {$user['id']}</div>
                <div class='user-name'>{$user['name']}</div>
                <div class='user-email'>{$user['email']}</div>
                <div>Member since: {$user['created_at']}</div>
            </div>
        </body>
        </html>";
    }
}