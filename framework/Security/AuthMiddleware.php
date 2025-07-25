<?php
declare(strict_types=1);

namespace Framework\Security;

use App\Domain\User\Services\AuthService;
use App\Domain\User\Enums\UserRole;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Http\HttpStatus;
use Framework\Routing\MiddlewareInterface;

/**
 * Auth Middleware - Authentifizierung und Autorisierung
 */
readonly class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService     $authService,
        private ResponseFactory $responseFactory
    )
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        // Prüfe ob User authentifiziert ist
        if (!$this->authService->isAuthenticated()) {
            return $this->handleUnauthenticated($request);
        }

        // Lade aktuellen User
        $user = $this->authService->getCurrentUser();
        if (!$user) {
            // Session inkonsistent - ausloggen
            $this->authService->logout();
            return $this->handleUnauthenticated($request);
        }

        // Prüfe User-Status
        if (!$user->canLogin()) {
            $this->authService->logout();
            return $this->handleInvalidStatus($request, $user->getStatus()->label());
        }

        // Request weiterleiten
        return $next($request);
    }

    /**
     * Behandelt nicht authentifizierte Requests
     */
    private function handleUnauthenticated(Request $request): Response
    {
        if ($request->expectsJson()) {
            return $this->responseFactory->json([
                'error' => 'Unauthenticated',
                'message' => 'Please log in to access this resource',
                'redirect' => '/login'
            ], HttpStatus::UNAUTHORIZED);
        }

        // Redirect zu Login-Seite
        return $this->responseFactory->redirect('/login?redirect=' . urlencode($request->getPath()));
    }

    /**
     * Behandelt User mit ungültigem Status
     */
    private function handleInvalidStatus(Request $request, string $status): Response
    {
        if ($request->expectsJson()) {
            return $this->responseFactory->json([
                'error' => 'Account Status Invalid',
                'message' => "Account status: {$status}",
                'status' => $status
            ], HttpStatus::FORBIDDEN);
        }

        return $this->responseFactory->view('auth/account-status', [
            'status' => $status,
            'message' => $this->getStatusMessage($status)
        ]);
    }

    /**
     * Status-spezifische Nachrichten
     */
    private function getStatusMessage(string $status): string
    {
        return match ($status) {
            'Nicht aktiviert' => 'Bitte bestätige deine E-Mail-Adresse, um deinen Account zu aktivieren.',
            'Gesperrt' => 'Dein Account wurde gesperrt. Bei Fragen wende dich an den Support.',
            default => 'Dein Account-Status erlaubt derzeit keinen Zugriff.'
        };
    }
}