<?php
declare(strict_types=1);
namespace App\Actions\Auth;

use App\Domain\User\Services\UserService;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;

/**
 * Verify Email Action - E-Mail-Verifikation
 */
#[Route(path: '/auth/verify-email', methods: ['GET'], name: 'auth.verify-email')]
readonly class VerifyEmailAction
{
    public function __construct(
        private UserService     $userService,
        private ResponseFactory $responseFactory
    ) {}

    public function __invoke(Request $request): Response
    {
        $token = $request->input('token');

        if (!$token) {
            return $this->responseFactory->view('auth/verify-email-error', [
                'title' => 'Ungültiger Link',
                'message' => 'Der Verifikations-Link ist ungültig oder unvollständig.',
            ]);
        }

        try {
            $user = $this->userService->verifyEmail($token);

            return $this->responseFactory->view('auth/verify-email-success', [
                'title' => 'E-Mail bestätigt',
                'username' => $user->getUsername()->toString(),
            ]);

        } catch (\DomainException $e) {
            return $this->responseFactory->view('auth/verify-email-error', [
                'title' => 'Verifikation fehlgeschlagen',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
