<?php

/**
 * Registration Action
 * Handles user registration for the football manager game
 *
 * File: src/Registration/Action/RegisterAction.php
 * Directory: /src/Registration/Action/
 */

declare(strict_types=1);

namespace Registration\Action;

use Framework\Core\Attributes\Route;
use Framework\Core\Logger;
use Framework\Security\CsrfProtection;
use Framework\Validation\Validator;
use Registration\Domain\RegistrationService;
use Registration\Responder\RegisterResponder;

#[Route('/register', 'GET', 'register.form')]
#[Route('/register', 'POST', 'register.submit', [CsrfProtection::class], 10)]
class RegisterAction
{
    public function __construct(
        private RegistrationService $registrationService,
        private RegisterResponder   $responder,
        private Validator           $validator,
        private Logger              $logger
    )
    {
    }

    /**
     * Handle registration request
     */
    public function __invoke(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            $this->showRegistrationForm();
        } else {
            $this->processRegistration();
        }
    }

    /**
     * Show registration form
     */
    private function showRegistrationForm(): void
    {
        $this->responder->showForm([
            'csrf_token' => $this->generateCsrfToken(),
            'errors' => [],
            'old_input' => []
        ]);
    }

    /**
     * Generate CSRF token
     */
    private function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Process registration submission
     */
    private function processRegistration(): void
    {
        $correlationId = $this->generateCorrelationId();

        $this->logger->info('Registration attempt started', [
            'correlation_id' => $correlationId,
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        try {
            // Validate input
            $validationResult = $this->validateInput($_POST);

            if (!$validationResult['valid']) {
                $this->logger->warning('Registration validation failed', [
                    'correlation_id' => $correlationId,
                    'errors' => $validationResult['errors']
                ]);

                $this->responder->showForm([
                    'csrf_token' => $this->generateCsrfToken(),
                    'errors' => $validationResult['errors'],
                    'old_input' => $this->sanitizeOldInput($_POST)
                ]);
                return;
            }

            // Process registration
            $registrationData = $this->prepareRegistrationData($validationResult['data']);
            $result = $this->registrationService->registerUser($registrationData);

            if ($result['success']) {
                $this->logger->info('User registration successful', [
                    'correlation_id' => $correlationId,
                    'user_id' => $result['user_id'],
                    'team_id' => $result['team_id'],
                    'league_id' => $result['league_id']
                ]);

                $this->responder->showSuccess($result);
            } else {
                $this->logger->error('User registration failed', [
                    'correlation_id' => $correlationId,
                    'error' => $result['error']
                ]);

                $this->responder->showForm([
                    'csrf_token' => $this->generateCsrfToken(),
                    'errors' => ['general' => $result['error']],
                    'old_input' => $this->sanitizeOldInput($_POST)
                ]);
            }

        } catch (\Throwable $e) {
            $this->logger->error('Registration process error', [
                'correlation_id' => $correlationId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->responder->showError('An unexpected error occurred. Please try again.');
        }
    }

    /**
     * Generate correlation ID for request tracking
     */
    private function generateCorrelationId(): string
    {
        return uniqid('reg_', true);
    }

    /**
     * Validate registration input
     */
    private function validateInput(array $input): array
    {
        $rules = [
            'trainer_name' => [
                'required' => true,
                'min_length' => 3,
                'max_length' => 50,
                'pattern' => '/^[a-zA-Z0-9_\-\s]+$/',
                'custom' => 'unique_trainer_name'
            ],
            'email' => [
                'required' => true,
                'email' => true,
                'max_length' => 255,
                'custom' => 'unique_email'
            ],
            'password' => [
                'required' => true,
                'min_length' => 8,
                'max_length' => 255,
                'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'description' => 'Password must contain at least one lowercase letter, one uppercase letter, one digit, and one special character'
            ],
            'password_confirmation' => [
                'required' => true,
                'matches' => 'password'
            ],
            'team_name' => [
                'required' => true,
                'min_length' => 3,
                'max_length' => 50,
                'pattern' => '/^[a-zA-Z0-9_\-\s]+$/',
                'custom' => 'valid_team_name'
            ],
            'terms_accepted' => [
                'required' => true,
                'accepted' => true
            ]
        ];

        return $this->validator->validate($input, $rules);
    }

    /**
     * Sanitize old input for redisplay
     */
    private function sanitizeOldInput(array $input): array
    {
        return [
            'trainer_name' => htmlspecialchars($input['trainer_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'email' => htmlspecialchars($input['email'] ?? '', ENT_QUOTES, 'UTF-8'),
            'team_name' => htmlspecialchars($input['team_name'] ?? '', ENT_QUOTES, 'UTF-8')
        ];
    }

    /**
     * Prepare registration data for service
     */
    private function prepareRegistrationData(array $data): array
    {
        return [
            'trainer_name' => trim($data['trainer_name']),
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
            'team_name' => trim($data['team_name']),
            'registration_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'registration_time' => time()
        ];
    }
}