<?php
declare(strict_types=1);
namespace App\Actions\Auth;

use App\Domain\User\Services\UserService;
use App\Domain\User\Services\AuthService;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Routing\Route;
use Framework\Validation\Validator;
use Framework\Security\Csrf;

/**
 * Login Action - Benutzer-Anmeldung
 */
#[Route(path: '/login', methods: ['GET', 'POST'], name: 'auth.login')]
class LoginAction
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AuthService $authService,
        private readonly ResponseFactory $responseFactory,
        private readonly Validator $validator,
        private readonly Csrf $csrf
    ) {}

    public function __invoke(Request $request): Response
    {
        // Bereits eingeloggte User weiterleiten
        if ($this->authService->isAuthenticated()) {
            return $this->responseFactory->redirect('/dashboard');
        }

        if ($request->isGet()) {
            return $this->showForm($request);
        }

        return $this->processLogin($request);
    }

    /**
     * Zeigt Login-Formular
     */
    private function showForm(Request $request): Response
    {
        $redirectUrl = $request->query('redirect', '/dashboard');

        return $this->responseFactory->view('auth/login', [
            'title' => 'Anmelden',
            'csrf_token' => $this->csrf->getToken(),
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Verarbeitet Login
     */
    private function processLogin(Request $request): Response
    {
        $data = $request->all();
        $redirectUrl = $data['redirect_url'] ?? '/dashboard';

        // Validierung
        $validation = $this->validator->validate($data, [
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validation->fails()) {
            return $this->responseFactory->view('auth/login', [
                'title' => 'Anmelden',
                'csrf_token' => $this->csrf->getToken(),
                'errors' => $validation->errors(),
                'old' => $data,
                'redirect_url' => $redirectUrl,
            ]);
        }

        try {
            // User authentifizieren
            $user = $this->userService->authenticateUser(
                $data['identifier'],
                $data['password']
            );

            // Login in Session
            $this->authService->login($user);

            // Weiterleitung
            return $this->responseFactory->redirect($redirectUrl);

        } catch (\DomainException $e) {
            return $this->responseFactory->view('auth/login', [
                'title' => 'Anmelden',
                'csrf_token' => $this->csrf->getToken(),
                'error' => $e->getMessage(),
                'old' => $data,
                'redirect_url' => $redirectUrl,
            ]);
        }
    }
}