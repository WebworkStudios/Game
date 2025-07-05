<?php

/**
 * Registration Responder
 * Handles response formatting for registration process
 *
 * File: src/Registration/Responder/RegisterResponder.php
 * Directory: /src/Registration/Responder/
 */

declare(strict_types=1);

namespace Registration\Responder;

use Framework\Core\TemplateEngine;
use Framework\Http\Response;

class RegisterResponder
{
    private TemplateEngine $templateEngine;

    public function __construct(TemplateEngine $templateEngine)
    {
        $this->templateEngine = $templateEngine;
    }

    /**
     * Show registration form
     */
    public function showForm(array $data): void
    {
        $this->templateEngine->render('registration/register_form', [
            'title' => 'Register for Football Manager',
            'csrf_token' => $data['csrf_token'],
            'errors' => $data['errors'],
            'old_input' => $data['old_input']
        ]);
    }

    /**
     * Show registration success
     */
    public function showSuccess(array $result): void
    {
        $this->templateEngine->render('registration/register_success', [
            'title' => 'Registration Successful',
            'message' => $result['message'],
            'user_id' => $result['user_id'],
            'team_id' => $result['team_id'],
            'league_id' => $result['league_id']
        ]);
    }

    /**
     * Show registration error
     */
    public function showError(string $message): void
    {
        http_response_code(500);

        $this->templateEngine->render('registration/register_error', [
            'title' => 'Registration Error',
            'error_message' => $message
        ]);
    }

    /**
     * API Response for JSON requests
     */
    public function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Show email confirmation result
     */
    public function showEmailConfirmation(array $result): void
    {
        if ($result['success']) {
            $this->templateEngine->render('registration/email_confirmed', [
                'title' => 'Email Confirmed',
                'message' => $result['message']
            ]);
        } else {
            $this->templateEngine->render('registration/email_confirmation_error', [
                'title' => 'Email Confirmation Error',
                'error_message' => $result['error']
            ]);
        }
    }
}