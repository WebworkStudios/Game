<?php
declare(strict_types=1);
namespace App\Actions\Auth;

use App\Domain\User\Services\AuthService;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;

/**
 * Logout Action - Benutzer-Abmeldung
 */
#[Route(path: '/logout', methods: ['POST'], name: 'auth.logout')]
class LogoutAction
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ResponseFactory $responseFactory
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->authService->logout();

        if ($request->expectsJson()) {
            return $this->responseFactory->json([
                'message' => 'Successfully logged out',
                'redirect' => '/login'
            ]);
        }

        return $this->responseFactory->redirect('/login?message=logged_out');
    }
}