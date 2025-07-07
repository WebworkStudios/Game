<?php
declare(strict_types=1);

namespace Registration\Domain;

class RegistrationData
{
    public function __construct(
        public readonly string $trainerName,
        public readonly string $email,
        public readonly string $password,
        public readonly string $passwordConfirmation,
        public readonly bool $termsAccepted,
        public readonly bool $newsletterSubscribed
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            trainerName: trim($data['trainer_name'] ?? ''),
            email: trim($data['email'] ?? ''),
            password: $data['password'] ?? '',
            passwordConfirmation: $data['password_confirmation'] ?? '',
            termsAccepted: isset($data['terms_accepted']),
            newsletterSubscribed: isset($data['newsletter_subscribed'])
        );
    }
}