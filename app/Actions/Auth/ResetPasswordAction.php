<?php
declare(strict_types=1);
namespace App\Actions\Auth;

use App\Domain\User\Services\UserService;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;
use Framework\Validation\Validator;
use Framework\Security\Csrf;

/**
 * Reset Password Action - Passwort zurücksetzen
 */
#[Route(path: '/auth/reset-password', methods: ['GET', 'POST'], name: 'auth.reset-password')]
class ResetPasswordAction
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ResponseFactory $responseFactory,
        private readonly Validator $validator,
        private readonly Csrf $csrf
    ) {}

    public function __invoke(Request $request): Response
    {
        $token = $request->query('token');

        if (!$token) {
            return $this->responseFactory->redirect('/forgot-password');
        }

        if ($request->isGet()) {
            return $this->showForm($token);
        }

        return $this->processReset($request, $token);
    }

    /**
     * Zeigt Reset-Formular
     */
    private function showForm(string $token): Response
    {
        return $this->responseFactory->view('auth/reset-password', [
            'title' => 'Neues Passwort setzen',
            'csrf_token' => $this->csrf->getToken(),
            'token' => $token,
        ]);
    }

    /**
     * Verarbeitet Passwort-Reset
     */
    private function processReset(Request $request, string $token): Response
    {
        $data = $request->all();

        // Validierung
        $validation = $this->validator->validate($data, [
            'password' => 'required|string|min:8|max:128',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validation->fails()) {
            return $this->responseFactory->view('auth/reset-password', [
                'title' => 'Neues Passwort setzen',
                'csrf_token' => $this->csrf->getToken(),
                'token' => $token,
                'errors' => $validation->errors(),
            ]);
        }

        try {
            $this->userService->resetPassword($token, $data['password']);

            return $this->responseFactory->view('auth/reset-password-success', [
                'title' => 'Passwort geändert',
            ]);

        } catch (\DomainException $e) {
            return $this->responseFactory->view('auth/reset-password', [
                'title' => 'Neues Passwort setzen',
                'csrf_token' => $this->csrf->getToken(),
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
        }
    }
}