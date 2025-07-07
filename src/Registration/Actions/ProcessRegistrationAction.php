<?php

// src/Registration/Actions/ProcessRegistrationAction.php

declare(strict_types=1);

namespace Registration\Actions;

use Framework\Core\Attributes\Route;
use Framework\Security\CsrfProtection;
use Framework\Security\RateLimiter;
use Registration\Domain\RegistrationData;
use Registration\Domain\Services\RegistrationService;
use Registration\Domain\Services\EmailVerificationService;
use Registration\Domain\Services\RegistrationValidationException;
use Registration\Responder\RegistrationResponder;

#[Route('/register', 'POST', 'registration.submit')]
class ProcessRegistrationAction
{
    private RegistrationService $registrationService;
    private EmailVerificationService $emailService;
    private RegistrationResponder $responder;
    private CsrfProtection $csrf;
    private RateLimiter $rateLimiter;

    public function __construct(
        RegistrationService $registrationService,
        EmailVerificationService $emailService,
        RegistrationResponder $responder,
        CsrfProtection $csrf,
        RateLimiter $rateLimiter
    ) {
        $this->registrationService = $registrationService;
        $this->emailService = $emailService;
        $this->responder = $responder;
        $this->csrf = $csrf;
        $this->rateLimiter = $rateLimiter;
    }

    public function __invoke(): void
    {
        // Security checks in action
        if (!$this->rateLimiter->allowRequest(5, 'registration', 3600)) {
            $this->responder->rateLimitExceeded();
            return;
        }

        if (!$this->csrf->handle()) {
            return;
        }

        try {
            // Business logic in service (including validation)
            $registrationData = RegistrationData::fromArray($_POST);
            $user = $this->registrationService->register($registrationData);
            $this->emailService->sendVerificationEmail($user);

            // Response handling
            $this->responder->registrationSuccess();

        } catch (RegistrationValidationException $e) {
            $this->responder->registrationFailed($e->getErrors(), $_POST);
        } catch (\Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            $this->responder->registrationFailed(
                ['general' => ['Registration failed. Please try again.']],
                $_POST
            );
        }
    }
}