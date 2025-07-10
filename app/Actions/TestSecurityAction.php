<?php


declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Test Action für Security-Features
 */
#[Route(path: '/test/security', methods: ['GET', 'POST'], name: 'test.security')]
class TestSecurityAction
{
    public function __construct(
        private readonly Application $app
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        $session = $this->app->getSession();
        $csrf = $this->app->getCsrf();

        if ($request->isPost()) {
            // POST-Request verarbeiten
            $session->flashSuccess('Form submitted successfully!');
            return Response::redirect('/test/security');
        }

        // GET-Request: Formular anzeigen
        $html = $this->renderSecurityTestPage($csrf, $session);
        return Response::ok($html);
    }

    private function renderSecurityTestPage($csrf, $session): string
    {
        $csrfField = $csrf->getTokenField();
        $csrfMeta = $csrf->getTokenMeta();
        $tokenInfo = $csrf->getTokenInfo();
        $sessionStatus = $session->getStatus();
        $flashMessages = $session->getAllFlash();

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Security Test Page</title>
            <meta charset='utf-8'>
            {$csrfMeta}
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .token-info { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; }
                .flash { padding: 10px; border-radius: 5px; margin: 10px 0; }
                .flash.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                .flash.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                form { margin: 20px 0; }
                input, button { margin: 5px 0; padding: 8px; }
                button { background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
                button:hover { background: #0056b3; }
            </style>
        </head>
        <body>
            <h1>🔐 Security Test Page</h1>

            " . $this->renderFlashMessages($flashMessages) . "

            <div class='section'>
                <h2>CSRF Protection Test</h2>
                <form method='POST'>
                    {$csrfField}
                    <div>
                        <label>Test Input:</label><br>
                        <input type='text' name='test_input' placeholder='Enter something...'>
                    </div>
                    <div>
                        <button type='submit'>Submit with CSRF Token</button>
                    </div>
                </form>

                <form method='POST' action='/test/security'>
                    <!-- Kein CSRF-Token - sollte fehlschlagen -->
                    <div>
                        <label>Unsafe Form (no CSRF token):</label><br>
                        <input type='text' name='unsafe_input' placeholder='This will fail...'>
                    </div>
                    <div>
                        <button type='submit'>Submit without CSRF Token</button>
                    </div>
                </form>
            </div>

            <div class='section'>
                <h2>Session Test</h2>
                <p><strong>Session ID:</strong> {$sessionStatus['id']}</p>
                <p><strong>Session Status:</strong> {$sessionStatus['status']}</p>
                
                <div>
                    <button onclick='setSessionValue()'>Set Session Value</button>
                    <button onclick='getSessionValue()'>Get Session Value</button>
                    <button onclick='clearSession()'>Clear Session</button>
                </div>
                <div id='session-result'></div>
            </div>

            <div class='section'>
                <h2>CSRF Token Information</h2>
                <div class='token-info'>
                    <pre>" . json_encode($tokenInfo, JSON_PRETTY_PRINT) . "</pre>
                </div>
            </div>

            <div class='section'>
                <h2>JavaScript CSRF Example</h2>
                <button onclick='makeAjaxRequest()'>Make AJAX Request with CSRF</button>
                <div id='ajax-result'></div>
            </div>

            <script>
                // CSRF-Token für JavaScript holen
                const csrfToken = document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content');

                function setSessionValue() {
                    fetch('/test/security', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            _token: csrfToken,
                            action: 'set_session',
                            key: 'test_key',
                            value: 'Hello from JavaScript! ' + Date.now()
                        })
                    }).then(response => {
                        document.getElementById('session-result').innerHTML = 'Session value set!';
                    });
                }

                function makeAjaxRequest() {
                    fetch('/test/security', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            _token: csrfToken,
                            ajax_test: true,
                            message: 'Hello from AJAX!'
                        })
                    })
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('ajax-result').innerHTML = 'AJAX successful!';
                    })
                    .catch(error => {
                        document.getElementById('ajax-result').innerHTML = 'AJAX failed: ' + error;
                    });
                }
            </script>
        </body>
        </html>";
    }

    private function renderFlashMessages(array $messages): string
    {
        if (empty($messages)) {
            return '';
        }

        $html = '';
        foreach ($messages as $type => $message) {
            $html .= "<div class='flash {$type}'>{$message}</div>";
        }

        return $html;
    }
}