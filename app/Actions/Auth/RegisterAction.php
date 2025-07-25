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
 * Register Action - Benutzer-Registrierung
 */
#[Route(path: '/register', methods: ['GET', 'POST'], name: 'auth.register')]
readonly class RegisterAction
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

        return $this->processRegistration($request);
    }

    /**
     * Zeigt Registrierungs-Formular
     */
    private function showForm(): Response
    {
        return $this->responseFactory->view('auth/register', [
            'title' => 'Registrierung',
            'csrf_token' => $this->csrf->getToken(),
        ]);
    }

    /**
     * Verarbeitet Registrierung
     */
    private function processRegistration(Request $request): Response
    {
        $data = $request->all();

        // Validierung
        $validation = $this->validator->validate($data, [
            'username' => 'required|string|min:3|max:50|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:128',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validation->fails()) {
            return $this->responseFactory->view('auth/register', [
                'title' => 'Registrierung',
                'csrf_token' => $this->csrf->getToken(),
                'errors' => $validation->errors(),
                'old' => $data,
            ]);
        }

        try {
            // User registrieren
            $user = $this->userService->registerUser(
                $data['username'],
                $data['email'],
                $data['password']
            );

            // Erfolgs-Nachricht
            return $this->responseFactory->view('auth/register-success', [
                'title' => 'Registrierung erfolgreich',
                'email' => $user->getEmail()->toString(),
            ]);

        } catch (\DomainException $e) {
            return $this->responseFactory->view('auth/register', [
                'title' => 'Registrierung',
                'csrf_token' => $this->csrf->getToken(),
                'error' => $e->getMessage(),
                'old' => $data,
            ]);
        }
    }
}