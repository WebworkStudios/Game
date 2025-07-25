<?php
declare(strict_types=1);
namespace App\Actions\Profile;

use App\Domain\User\Services\AuthService;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;
use Framework\Security\AuthMiddleware;

/**
 * Show Profile Action - Profil anzeigen
 */
#[Route(path: '/profile', methods: ['GET'], name: 'profile.show', middlewares: [AuthMiddleware::class])]
class ShowProfileAction
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ResponseFactory $responseFactory
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();

        return $this->responseFactory->view('profile/show', [
            'title' => 'Mein Profil',
            'user' => $user,
        ]);
    }
}
