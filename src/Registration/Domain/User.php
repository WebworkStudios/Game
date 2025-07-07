<?php
declare(strict_types=1);

namespace Registration\Domain;

class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $trainerName,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly UserRole $role,
        public readonly float $actionDollars,
        public readonly bool $isEmailVerified,
        public readonly ?string $emailVerificationToken,
        public readonly ?\DateTime $emailVerificationExpires,
        public readonly bool $newsletterSubscribed,
        public readonly \DateTime $createdAt,
        public readonly \DateTime $updatedAt
    ) {}

    public static function create(
        string $trainerName,
        string $email,
        string $passwordHash,
        bool $newsletterSubscribed = false
    ): self {
        return new self(
            id: null,
            trainerName: $trainerName,
            email: strtolower($email),
            passwordHash: $passwordHash,
            role: UserRole::USER,
            actionDollars: 1000.00,
            isEmailVerified: false,
            emailVerificationToken: bin2hex(random_bytes(32)),
            emailVerificationExpires: new \DateTime('+24 hours'),
            newsletterSubscribed: $newsletterSubscribed,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime()
        );
    }

    public function activate(): self
    {
        return new self(
            id: $this->id,
            trainerName: $this->trainerName,
            email: $this->email,
            passwordHash: $this->passwordHash,
            role: $this->role,
            actionDollars: $this->actionDollars,
            isEmailVerified: true,
            emailVerificationToken: null,
            emailVerificationExpires: null,
            newsletterSubscribed: $this->newsletterSubscribed,
            createdAt: $this->createdAt,
            updatedAt: new \DateTime()
        );
    }

    public function isVerificationTokenValid(): bool
    {
        return $this->emailVerificationToken !== null
            && $this->emailVerificationExpires !== null
            && $this->emailVerificationExpires > new \DateTime()
            && !$this->isEmailVerified;
    }
}