<?php
declare(strict_types=1);
namespace App\Actions\Auth;

use App\Domain\User\Services\UserService;
use App\Domain\User\Services\AuthService;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Http\HttpMethod;
use Framework\Routing\Route;
use Framework\Validation\Validator;
use Framework\Security\Csrf;

/**
 * Login Action - Benutzer-Anmeldung
 */
#[Route(path: '/login', methods: ['GET', 'POST'], name: 'auth.login')]
readonly class LoginAction
{
    public function __construct(
        private UserService     $userService,
        private AuthService     $authService,
        private ResponseFactory $responseFactory,
        private Validator       $validator,
        private Csrf            $csrf
    ) {}

    public function __invoke(Request $request): Response
    {
        // Bereits eingeloggte User weiterleiten
        if ($this->authService->isAuthenticated()) {
            return $this->responseFactory->redirect('/dashboard');
        }

        // HTTP-Methode prüfen - KORREKTE Framework-Syntax
        if ($request->getMethod() === HttpMethod::GET) {
            return $this->showForm($request);
        }

        return $this->processLogin($request);
    }

    /**
     * Zeigt Login-Formular
     */
    private function showForm(Request $request): Response
    {
        // Query-Parameter korrekt abrufen - KORREKTE Framework-Syntax
        $redirectUrl = $request->input('redirect', '/dashboard');

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

        // CSRF-Token prüfen
        if (!$this->csrf->validateToken($data['_token'] ?? '')) {
            return $this->responseFactory->view('auth/login', [
                'title' => 'Anmelden',
                'csrf_token' => $this->csrf->getToken(),
                'redirect_url' => $redirectUrl,
                'error' => 'Ungültiger CSRF-Token. Bitte versuchen Sie es erneut.',
            ]);
        }

        // Validierung
        $validation = $this->validator->validate($data, [
            'identifier' => 'required|string',  // Kann E-Mail oder Username sein
            'password' => 'required|string|min:1',
        ]);

        if ($validation->fails()) {
            return $this->responseFactory->view('auth/login', [
                'title' => 'Anmelden',
                'csrf_token' => $this->csrf->getToken(),
                'redirect_url' => $redirectUrl,
                'errors' => $validation->errors(),
                'old' => $data,
            ]);
        }

        // Login-Versuch
        try {
            // User authentifizieren über UserService (nicht AuthService!)
            $user = $this->userService->authenticateUser(
                $data['identifier'],  // E-Mail oder Username
                $data['password']
            );

            // User in Session einloggen über AuthService
            $this->authService->login($user);

            // Bei erfolgreichem Login weiterleiten
            return $this->responseFactory->redirect($redirectUrl);

        } catch (\DomainException $e) {
            return $this->responseFactory->view('auth/login', [
                'title' => 'Anmelden',
                'csrf_token' => $this->csrf->getToken(),
                'redirect_url' => $redirectUrl,
                'error' => $e->getMessage(),
                'old' => ['identifier' => $data['identifier']],  // Korrigiert: identifier statt email
            ]);

        } catch (\Exception $e) {
            return $this->responseFactory->view('auth/login', [
                'title' => 'Anmelden',
                'csrf_token' => $this->csrf->getToken(),
                'redirect_url' => $redirectUrl,
                'error' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.',
            ]);
        }
    }
}