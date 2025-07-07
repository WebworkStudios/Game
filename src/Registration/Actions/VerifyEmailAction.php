<?php
// src/Registration/Actions/VerifyEmailAction.php

declare(strict_types=1);

namespace Registration\Actions;

use Framework\Core\Attributes\Route;
use Registration\Domain\Services\RegistrationService;
use Registration\Domain\Services\InvalidVerificationTokenException;
use Registration\Responder\RegistrationResponder;

#[Route('/verify-email', 'GET', 'email.verification')]
class VerifyEmailAction
{
    private RegistrationService $registrationService;
    private RegistrationResponder $responder;

    public function __construct(
        RegistrationService $registrationService,
        RegistrationResponder $responder
    ) {
        $this->registrationService = $registrationService;
        $this->responder = $responder;
    }

    public function __invoke(): void
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $this->responder->activationFailed('Invalid verification link.');
            return;
        }

        try {
            $this->registrationService->activateUser($token);
            $this->responder->activationSuccess();
        } catch (InvalidVerificationTokenException $e) {
            $this->responder->activationFailed($e->getMessage());
        } catch (\Exception $e) {
            error_log('Email verification error: ' . $e->getMessage());
            $this->responder->activationFailed('Verification failed. Please try again.');
        }
    }
}