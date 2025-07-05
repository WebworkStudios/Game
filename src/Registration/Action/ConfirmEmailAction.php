<?php

/**
 * Email Confirmation Action
 * Handles email confirmation for user accounts
 *
 * File: src/Registration/Action/ConfirmEmailAction.php
 * Directory: /src/Registration/Action/
 */

declare(strict_types=1);

namespace Registration\Action;

use Framework\Core\Attributes\Route;
use Framework\Core\Logger;
use Registration\Domain\RegistrationService;
use Registration\Responder\RegisterResponder;

#[Route('/confirm-email/{token}', 'GET', 'email.confirm')]
class ConfirmEmailAction
{
    public function __construct(
        private RegistrationService $registrationService,
        private RegisterResponder   $responder,
        private Logger              $logger
    )
    {
    }

    /**
     * Handle email confirmation
     */
    public function __invoke(string $token): void
    {
        $correlationId = $this->generateCorrelationId();

        $this->logger->info('Email confirmation attempt', [
            'correlation_id' => $correlationId,
            'token' => substr($token, 0, 8) . '...',
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        try {
            // Validate token format
            if (!$this->isValidTokenFormat($token)) {
                $this->logger->warning('Invalid token format provided', [
                    'correlation_id' => $correlationId,
                    'token_length' => strlen($token)
                ]);

                $this->responder->showEmailConfirmation([
                    'success' => false,
                    'error' => 'Invalid confirmation token format.'
                ]);
                return;
            }

            // Process email confirmation
            $result = $this->registrationService->confirmEmail($token);

            if ($result['success']) {
                $this->logger->info('Email confirmation successful', [
                    'correlation_id' => $correlationId
                ]);
            } else {
                $this->logger->warning('Email confirmation failed', [
                    'correlation_id' => $correlationId,
                    'error' => $result['error']
                ]);
            }

            $this->responder->showEmailConfirmation($result);

        } catch (\Throwable $e) {
            $this->logger->error('Email confirmation process error', [
                'correlation_id' => $correlationId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->responder->showEmailConfirmation([
                'success' => false,
                'error' => 'An unexpected error occurred during email confirmation.'
            ]);
        }
    }

    /**
     * Generate correlation ID for request tracking
     */
    private function generateCorrelationId(): string
    {
        return uniqid('confirm_', true);
    }

    /**
     * Validate token format
     */
    private function isValidTokenFormat(string $token): bool
    {
        // Token should be 64 character hex string
        return strlen($token) === 64 && ctype_xdigit($token);
    }
}