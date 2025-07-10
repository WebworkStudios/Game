<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\HttpStatus;
use Framework\Routing\MiddlewareInterface;
use Framework\Routing\RouterCache;
use ReflectionClass;
use ReflectionException;

/**
 * CSRF Middleware - Automatische CSRF-Validierung für state-changing Requests
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly RouterCache $routerCache
    ) {}

    /**
     * Verarbeitet Request und validiert CSRF-Token
     */
    public function handle(Request $request, callable $next): Response
    {
        if ($this->csrf->requiresValidation($request)) {


            if ($this->isExempt($request)) {
                return $next($request);
            }

            if (!$this->csrf->validateToken($request)) {
                return $this->handleCsrfFailure($request);
            }

        }
        return $next($request);
    }

    /**
     * Prüft ob Request von CSRF-Validierung ausgenommen ist
     */
    private function isExempt(Request $request): bool
    {
        $path = $request->getPath();

        // API-Endpunkte können ausgenommen werden (nutzen andere Auth-Methoden)
        if (str_starts_with($path, '/api/')) {
            return true;
        }

        // Webhook-Endpunkte
        if (str_starts_with($path, '/webhooks/')) {
            return true;
        }

        // Testing-Endpunkte (in Development) - aber NICHT /test/security
        if (str_starts_with($path, '/test/') && $path !== '/test/security') {
            return true;
        }

        // Spezielle Prüfung für /test/security
        if ($path === '/test/security') {
            // Fahre mit Attribute-Prüfung fort
        }

        // Prüfe CsrfExempt Attribute an der Action-Klasse
        if ($this->hasCsrfExemptAttribute($request)) {
            return true;
        }
        return false;
    }

    /**
     * Prüft ob Action-Klasse das CsrfExempt-Attribute hat
     */
    private function hasCsrfExemptAttribute(Request $request): bool
    {
        try {
            $actionClass = $this->findActionClass($request);

            if ($actionClass === null) {
                return false;
            }

            $reflection = new ReflectionClass($actionClass);
            $attributes = $reflection->getAttributes(CsrfExempt::class);

            return !empty($attributes);

        } catch (ReflectionException) {
            // Bei Fehlern in der Reflection: nicht ausschließen (sicherer)
            return false;
        }
    }

    /**
     * Findet die Action-Klasse für den aktuellen Request
     */
    private function findActionClass(Request $request): ?string
    {
        try {
            // Lade alle Routes
            $routes = $this->routerCache->loadRouteEntries();

            $path = $request->getPath();
            $method = $request->getMethod();

            foreach ($routes as $route) {
                // Prüfe ob Route mit dem Request übereinstimmt
                if ($route->supportsMethod($method)) {
                    $matches = $route->matches($path);

                    if ($matches !== false) {
                        return $route->action;
                    }
                }
            }

            return null;

        } catch (\Exception) {
            // Bei Fehlern: null zurückgeben (keine Ausnahme)
            return null;
        }
    }

    /**
     * Behandelt CSRF-Validierungsfehler
     */
    private function handleCsrfFailure(Request $request): Response
    {
        // JSON-Response für API-Requests
        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'CSRF token validation failed',
                'message' => 'The request could not be completed due to invalid security token.',
                'code' => 419
            ], HttpStatus::PAGE_EXPIRED);  //
        }

        // HTML-Response für Web-Requests
        return $this->createCsrfErrorResponse();
    }

    /**
     * Erstellt CSRF-Fehler Response mit korrektem Status
     */
    private function createCsrfErrorResponse(): Response
    {
        $html = $this->renderCsrfErrorPage();

        return new Response(
            status: HttpStatus::PAGE_EXPIRED,  // ← Geändert von HttpStatus::from(419)
            headers: ['Content-Type' => 'text/html; charset=UTF-8'],
            body: $html
        );
    }

    /**
     * Rendert CSRF-Fehlerseite
     */
    private function renderCsrfErrorPage(): string
    {
        return "
        <!DOCTYPE html>
        <html lang=de>
        <head>
            <title>419 - CSRF Token Mismatch</title>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #333;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    max-width: 500px;
                    width: 90%;
                    text-align: center;
                }
                .code { 
                    font-size: 4em; 
                    font-weight: bold; 
                    color: #e74c3c; 
                    margin-bottom: 20px;
                    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
                }
                .title { 
                    font-size: 1.5em; 
                    margin-bottom: 15px; 
                    color: #333;
                    font-weight: 600;
                }
                .message {
                    color: #666;
                    margin-bottom: 25px;
                    line-height: 1.6;
                    font-size: 1.1em;
                }
                .details {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                    border-left: 4px solid #e74c3c;
                }
                .details h4 {
                    color: #e74c3c;
                    margin-bottom: 10px;
                    font-size: 1.1em;
                }
                .details ul {
                    text-align: left;
                    color: #555;
                    line-height: 1.5;
                }
                .details li {
                    margin: 5px 0;
                }
                .actions {
                    margin-top: 30px;
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .btn {
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    display: inline-block;
                    min-width: 120px;
                }
                .btn-primary {
                    background: #007bff;
                    color: white;
                }
                .btn-primary:hover {
                    background: #0056b3;
                    transform: translateY(-2px);
                }
                .btn-secondary {
                    background: #6c757d;
                    color: white;
                }
                .btn-secondary:hover {
                    background: #545b62;
                    transform: translateY(-2px);
                }
                .security-info {
                    margin-top: 20px;
                    padding: 15px;
                    background: #e8f4fd;
                    border-radius: 8px;
                    font-size: 0.9em;
                    color: #0c5460;
                }
                @media (max-width: 480px) {
                    .container { padding: 25px; }
                    .code { font-size: 3em; }
                    .actions { flex-direction: column; }
                    .btn { width: 100%; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='code'>419</div>
                <div class='title'>CSRF Token Mismatch</div>
                <div class='message'>
                    Your request could not be processed due to a security token mismatch.
                </div>
                
                <div class='details'>
                    <h4>This usually happens when:</h4>
                    <ul>
                        <li>Your session has expired</li>
                        <li>The form was submitted from an untrusted source</li>
                        <li>You've been idle for too long</li>
                        <li>Multiple browser tabs are interfering</li>
                    </ul>
                </div>

                <div class='actions'>
                    <a href='javascript:history.back()' class='btn btn-secondary'>← Go Back</a>
                    <a href='javascript:window.location.reload()' class='btn btn-primary'>Refresh Page</a>
                    <a href='/' class='btn btn-secondary'>Home</a>
                </div>

                <div class='security-info'>
                    🔒 This security measure protects you from cross-site request forgery attacks.
                </div>
            </div>

             <script>
            // CSRF-Token für JavaScript holen
            const csrfToken = document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content');

            // Auto-refresh nach 5 Sekunden falls JavaScript aktiviert
            let countdown = 5;
            const refreshBtn = document.querySelector('.btn-primary');
            const originalText = refreshBtn.textContent;
            
            const timer = setInterval(() => {
                refreshBtn.textContent = `Refresh (\${countdown}s)`; 
                countdown--;
                
                if (countdown < 0) {
                    clearInterval(timer);
                    window.location.reload();
                }
            }, 1000);
            
            // Stop countdown wenn User interagiert
            document.addEventListener('click', () => clearInterval(timer));
            document.addEventListener('keypress', () => clearInterval(timer));
        </script>
        </body>
        </html>";
    }
}