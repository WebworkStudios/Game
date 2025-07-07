<?php
// src/Registration/Services/EmailVerificationService.php

declare(strict_types=1);

namespace Registration\Domain\Services;

use Framework\Email\EmailService;
use Registration\Domain\User;

class EmailVerificationService
{
    private EmailService $emailService;
    private string $appUrl;

    public function __construct(EmailService $emailService, string $appUrl)
    {
        $this->emailService = $emailService;
        $this->appUrl = $appUrl;
    }

    public function sendVerificationEmail(User $user): bool
    {
        if (!$user->emailVerificationToken) {
            throw new \InvalidArgumentException('User has no verification token');
        }

        $verificationUrl = $this->appUrl . '/verify-email?token=' . $user->emailVerificationToken;

        $emailData = [
            'to' => $user->email,
            'to_name' => $user->trainerName,
            'subject' => 'Activate your Kickerscup account',
            'template' => 'verification',
            'data' => [
                'trainer_name' => $user->trainerName,
                'verification_url' => $verificationUrl,
                'app_name' => 'Kickerscup'
            ]
        ];

        return $this->emailService->send($emailData);
    }
}