<?php
// src/Registration/Responder/RegistrationResponder.php

declare(strict_types=1);

namespace Registration\Responder;

use Framework\Core\TemplateEngine;
use Framework\Core\SessionManagerInterface;
use Framework\Security\CsrfProtection;

class RegistrationResponder
{
    private TemplateEngine $templates;
    private SessionManagerInterface $session;
    private CsrfProtection $csrf;

    public function __construct(
        TemplateEngine $templates,
        SessionManagerInterface $session,
        CsrfProtection $csrf
    ) {
        $this->templates = $templates;
        $this->session = $session;
        $this->csrf = $csrf;
    }

    public function showForm(array $formData = [], array $errors = []): void
    {
        $data = [
            'page_title' => 'Join Kickerscup',
            'csrf_token' => $this->csrf->generateToken(),
            'csrf_field' => $this->csrf->getTokenField(),
            'form_data' => $formData,
            'errors' => $errors,
            'registration_enabled' => true
        ];

        $this->templates->render('registration/register', $data);
    }

    public function registrationSuccess(): void
    {
        $this->session->flash('success', 'Registration successful! Please check your email to activate your account.');
        $this->redirect('/login');
    }

    public function registrationFailed(array $errors, array $formData = []): void
    {
        $this->showForm($formData, $errors);
    }

    public function rateLimitExceeded(): void
    {
        $this->session->flash('error', 'Too many registration attempts. Please try again later.');
        $this->showForm();
    }

    public function activationSuccess(): void
    {
        $this->session->flash('success', 'Account activated successfully! You can now sign in.');
        $this->redirect('/login');
    }

    public function activationFailed(string $message): void
    {
        $this->session->flash('error', $message);
        $this->redirect('/login');
    }

    private function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }
}