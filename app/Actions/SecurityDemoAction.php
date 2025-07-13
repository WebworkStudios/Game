<?php
// app/Actions/SecurityDemoAction.php

declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

#[Route(path: '/security-demo', methods: ['GET', 'POST'])]
class SecurityDemoAction
{
    public function __construct(
        private readonly Application $app
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        if ($request->isPost()) {
            // POST-Request verarbeiten (CSRF wird durch CsrfMiddleware validiert)
            return $this->handlePostRequest($request);
        }

        // GET-Request: Demo-Seite anzeigen
        return $this->showDemoPage();
    }

    private function handlePostRequest(Request $request): Response
    {
        $session = $this->app->getSession();
        $session->flashSuccess('Security Demo: Form submitted successfully!');

        return Response::redirect('/security-demo');
    }

    private function showDemoPage(): Response
    {
        // Simuliere verschiedene Arten von potentiell gefährlichen Eingaben
        $data = [
            // XSS-Payload Beispiele
            'malicious_input' => '<script>alert("XSS Attack!")</script>',
            'user_name' => 'Max Mustermann',
            'user_input' => '<img src="x" onerror="alert(\'XSS\')">',
            'search_term' => 'search query with special chars: <>&"\'',

            // Vertrauenswürdiger HTML-Content
            'trusted_html' => '<strong>Vertrauenswürdiger</strong> <em>HTML-Content</em>',

            // Demonstration verschiedener Kontexte
            'examples' => [
                [
                    'context' => 'HTML Content',
                    'input' => '<script>evil()</script>Hello World',
                    'safe_output' => '&lt;script&gt;evil()&lt;/script&gt;Hello World',
                ],
                [
                    'context' => 'HTML Attribute',
                    'input' => '" onmouseover="alert(1)"',
                    'safe_output' => '&quot; onmouseover=&quot;alert(1)&quot;',
                ],
                [
                    'context' => 'JavaScript String',
                    'input' => '</script><script>alert(1)</script>',
                    'safe_output' => '"<\/script><script>alert(1)<\/script>"',
                ],
            ],
        ];

        // ViewRenderer verwenden statt veraltete view() Methode
        $viewRenderer = $this->app->getViewRenderer();
        return $viewRenderer->render('pages/security-demo', $data);
    }
}