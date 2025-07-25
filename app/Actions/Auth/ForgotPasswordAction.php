<?php
declare(strict_types=1);
namespace App\Actions\Auth;

use App\Domain\User\Services\UserService;
use Framework\Http\HttpMethod;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;
use Framework\Validation\Validator;
use Framework\Security\Csrf;

/**
 * Forgot Password Action - Passwort vergessen
 */
#[Route(path: '/forgot-password', methods: ['GET', 'POST'], name: 'auth.forgot-password')]
readonly class ForgotPasswordAction
{
    public function __construct(
        private UserService     $userService,
        private ResponseFactory $responseFactory,
        private Validator       $validator,
        private Csrf            $csrf
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->getMethod() === HttpMethod::GET) {
            return $this->showForm();
        }

        return $this->processRequest($request);
    }

    /**
     * Zeigt Formular
     */
    private function showForm(): Response
    {
        return $this->responseFactory->view('auth/forgot-password', [
            'title' => 'Passwort vergessen',
            'csrf_token' => $this->csrf->getToken(),
        ]);
    }

    /**
     * Verarbeitet Reset-Anfrage
     */
    private function processRequest(Request $request): Response
    {
        $data = $request->all();

        // Validierung
        $validation = $this->validator->validate($data, [
            'email' => 'required|email',
        ]);

        if ($validation->fails()) {
            return $this->responseFactory->view('auth/forgot-password', [
                'title' => 'Passwort vergessen',
                'csrf_token' => $this->csrf->getToken(),
                'errors' => $validation->errors(),
                'old' => $data,
            ]);
        }

        // Reset-E-Mail senden (auch bei unbekannter E-Mail)
        $this->userService->requestPasswordReset($data['email']);

        return $this->responseFactory->view('auth/forgot-password-sent', [
            'title' => 'E-Mail gesendet',
            'email' => $data['email'],
        ]);
    }
}