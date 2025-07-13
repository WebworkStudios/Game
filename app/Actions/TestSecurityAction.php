<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Test Action für Security-Features des Frameworks
 *
 * Demonstriert:
 * - CSRF Protection (automatisch durch CsrfMiddleware)
 * - Session Security
 * - Input Validation
 * - Sichere Request/Response Behandlung
 */
#[Route(path: '/test/security', methods: ['GET', 'POST'], name: 'test.security')]
class TestSecurityAction
{
    public function __construct(
        private readonly Application $app
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->isPost()) {
            return $this->handlePostRequest($request);
        }

        // GET-Request: Security-Test-Seite anzeigen
        return $this->showSecurityTestPage($request);
    }

    /**
     * Behandelt POST-Requests (Form-Submit oder AJAX)
     */
    private function handlePostRequest(Request $request): Response
    {
        // AJAX/JSON-Request behandeln
        if ($request->expectsJson() || $request->isAjax()) {
            return $this->handleAjaxRequest($request);
        }

        // Normaler Form-Submit
        return $this->handleFormSubmit($request);
    }

    /**
     * Behandelt AJAX-Requests für Session- und Security-Tests
     */
    private function handleAjaxRequest(Request $request): Response
    {
        $session = $this->app->getSession();
        $data = $request->expectsJson() ? $request->json() : $request->all();
        $action = $data['action'] ?? 'test';

        switch ($action) {
            case 'set_session':
                $key = $data['key'] ?? 'test_key';
                $value = $data['value'] ?? 'Default value';

                // Input sanitization für Session-Werte
                $sanitizedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $session->set($key, $sanitizedValue);

                return Response::json([
                    'success' => true,
                    'message' => "Session value '{$key}' set successfully",
                    'original_value' => $value,
                    'sanitized_value' => $sanitizedValue
                ]);

            case 'get_session':
                $key = $data['key'] ?? 'test_key';
                $value = $session->get($key, 'Not found');

                return Response::json([
                    'success' => true,
                    'key' => $key,
                    'value' => $value,
                    'session_id' => $session->getId()
                ]);

            case 'clear_session':
                $session->clear();
                return Response::json([
                    'success' => true,
                    'message' => 'Session cleared successfully'
                ]);

            case 'test_xss':
                $input = $data['input'] ?? '<script>alert("XSS")</script>';
                $escaped = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

                return Response::json([
                    'success' => true,
                    'original' => $input,
                    'escaped' => $escaped,
                    'safe' => $escaped !== $input
                ]);

            case 'test_csrf':
                $csrf = $this->app->getCsrf();
                return Response::json([
                    'success' => true,
                    'csrf_token' => $csrf->getToken(),
                    'token_info' => $csrf->getTokenInfo(),
                    'message' => 'CSRF token retrieved successfully'
                ]);

            default:
                return Response::json([
                    'success' => true,
                    'message' => 'AJAX Security test successful',
                    'timestamp' => time()
                ]);
        }
    }

    /**
     * Behandelt normalen Form-Submit
     */
    private function handleFormSubmit(Request $request): Response
    {
        $session = $this->app->getSession();

        // Validation der Form-Daten
        try {
            $validated = $this->app->validateOrFail($request->all(), [
                'test_input' => 'required|string|max:1000',
                'user_name' => 'nullable|string|max:100',
                'email' => 'nullable|email'
            ]);

            // Sichere Verarbeitung der Eingaben
            $testInput = htmlspecialchars($validated['test_input'], ENT_QUOTES, 'UTF-8');
            $userName = htmlspecialchars($validated['user_name'] ?? '', ENT_QUOTES, 'UTF-8');

            // Session-Daten setzen
            $session->set('last_test_input', $testInput);
            $session->set('last_user_name', $userName);

            $session->flashSuccess('Security test completed successfully!');

        } catch (\Exception $e) {
            $session->flashError('Validation failed: ' . $e->getMessage());
        }

        return Response::redirect('/test/security');
    }

    /**
     * Zeigt die Security-Test-Seite an
     */
    private function showSecurityTestPage(Request $request): Response
    {
        $session = $this->app->getSession();
        $csrf = $this->app->getCsrf();

        // Test-Daten für verschiedene Security-Szenarien
        $testData = [
            'page_title' => 'Security Test Suite',
            'csrf_token' => $csrf->getToken(),
            'csrf_field' => $csrf->getTokenField(),
            'csrf_meta' => $csrf->getTokenMeta(),
            'csrf_info' => $csrf->getTokenInfo(),
            'session_status' => $session->getStatus(),
            'session_id' => $session->getId(),
            'flash_messages' => $this->getFlashMessages($session),
            'last_test_input' => $session->get('last_test_input', ''),
            'last_user_name' => $session->get('last_user_name', ''),

            // XSS-Test-Beispiele
            'xss_examples' => [
                [
                    'name' => 'Basic Script Tag',
                    'input' => '<script>alert("XSS")</script>',
                    'context' => 'HTML Content'
                ],
                [
                    'name' => 'Image with onerror',
                    'input' => '<img src="x" onerror="alert(\'XSS\')">',
                    'context' => 'HTML Content'
                ],
                [
                    'name' => 'Event Handler',
                    'input' => '" onmouseover="alert(1)"',
                    'context' => 'HTML Attribute'
                ],
                [
                    'name' => 'JavaScript URL',
                    'input' => 'javascript:alert("XSS")',
                    'context' => 'URL'
                ]
            ],

            // Sichere Test-Eingaben
            'safe_examples' => [
                'Normal text input' => 'Hello World!',
                'Special characters' => 'Special chars: <>&"\'',
                'HTML entities' => '&lt;strong&gt;Bold&lt;/strong&gt;',
                'Unicode' => 'Unicode: äöü ñ 中文'
            ],

            // Request-Informationen
            'request_info' => [
                'method' => $request->getMethod()->value,
                'path' => $request->getPath(),
                'is_ajax' => $request->isAjax(),
                'is_json' => $request->expectsJson(),
                'user_agent' => $request->getHeader('User-Agent'),
                'ip_address' => $request->ip(),
                'referrer' => $request->getHeader('Referer')
            ],

            // Security-Headers
            'security_headers' => [
                'X-Frame-Options' => 'DENY',
                'X-Content-Type-Options' => 'nosniff',
                'X-XSS-Protection' => '1; mode=block',
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
                'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline';"
            ]
        ];

        // ViewRenderer verwenden
        $viewRenderer = $this->app->getViewRenderer();
        $response = $viewRenderer->render('pages/security-test', $testData);

        // Security-Headers hinzufügen
        foreach ($testData['security_headers'] as $header => $value) {
            $response->withHeader($header, $value);
        }

        return $response;
    }

    /**
     * Holt Flash-Messages (kompatibel mit Ihrem Session-System)
     */
    private function getFlashMessages($session): array
    {
        $flashMessages = [];

        // Versuche verschiedene Flash-Message-Typen zu holen
        $types = ['success', 'error', 'warning', 'info'];

        foreach ($types as $type) {
            if ($session->hasFlash($type)) {
                $flashMessages[$type] = $session->getFlash($type);
            }
        }

        return $flashMessages;
    }

    /**
     * Hilfsmethode: Generiert sichere Demo-Inhalte
     */
    private function generateSecureContent(string $input): array
    {
        return [
            'original' => $input,
            'escaped' => htmlspecialchars($input, ENT_QUOTES, 'UTF-8'),
            'length' => strlen($input),
            'is_safe' => !preg_match('/<script|javascript:|on\w+=/i', $input),
            'contains_html' => $input !== strip_tags($input)
        ];
    }

    /**
     * Hilfsmethode: Validiert Security-Parameter
     */
    private function validateSecurityInput(array $data): array
    {
        $validator = $this->app->validate($data, [
            'test_input' => 'required|string|max:1000',
            'security_level' => 'in:low,medium,high',
            'enable_logging' => 'boolean',
            'user_name' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9\s\-_]+$/',
            'email' => 'nullable|email',
            'url' => 'nullable|url'
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Security validation failed: ' .
                implode(', ', $validator->errors()->all()));
        }

        return $validator->validated();
    }
}