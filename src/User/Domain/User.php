<?php

/**
 * User Domain Model
 * Represents a user entity in the football manager game
 *
 * File: src/User/Domain/User.php
 * Directory: /src/User/Domain/
 */

declare(strict_types=1);

namespace User\Domain;

use DateTimeImmutable;
use DateTimeInterface;

class User
{
    private int $id;
    private string $trainerName;
    private string $email;
    private string $passwordHash;
    private bool $emailVerified;
    private ?string $emailVerificationToken;
    private ?DateTimeInterface $emailVerifiedAt;
    private string $status;
    private ?string $passwordResetToken;
    private ?DateTimeInterface $passwordResetExpiresAt;
    private string $registrationIp;
    private string $userAgent;
    private ?DateTimeInterface $lastLoginAt;
    private ?string $lastLoginIp;
    private ?string $lastLoginUserAgent;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    private ?DateTimeInterface $deletedAt;

    public function __construct(
        int                $id,
        string             $trainerName,
        string             $email,
        string             $passwordHash,
        bool               $emailVerified = false,
        ?string            $emailVerificationToken = null,
        ?DateTimeInterface $emailVerifiedAt = null,
        string             $status = 'pending_verification',
        ?string            $passwordResetToken = null,
        ?DateTimeInterface $passwordResetExpiresAt = null,
        string             $registrationIp = '',
        string             $userAgent = '',
        ?DateTimeInterface $lastLoginAt = null,
        ?string            $lastLoginIp = null,
        ?string            $lastLoginUserAgent = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null,
        ?DateTimeInterface $deletedAt = null
    )
    {
        $this->id = $id;
        $this->trainerName = $trainerName;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->emailVerified = $emailVerified;
        $this->emailVerificationToken = $emailVerificationToken;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->status = $status;
        $this->passwordResetToken = $passwordResetToken;
        $this->passwordResetExpiresAt = $passwordResetExpiresAt;
        $this->registrationIp = $registrationIp;
        $this->userAgent = $userAgent;
        $this->lastLoginAt = $lastLoginAt;
        $this->lastLoginIp = $lastLoginIp;
        $this->lastLoginUserAgent = $lastLoginUserAgent;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->deletedAt = $deletedAt;
    }

    /**
     * Create User from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            trainerName: $data['trainer_name'],
            email: $data['email'],
            passwordHash: $data['password_hash'],
            emailVerified: (bool)$data['email_verified'],
            emailVerificationToken: $data['email_verification_token'] ?? null,
            emailVerifiedAt: $data['email_verified_at'] ? new DateTimeImmutable($data['email_verified_at']) : null,
            status: $data['status'],
            passwordResetToken: $data['password_reset_token'] ?? null,
            passwordResetExpiresAt: $data['password_reset_expires_at'] ? new DateTimeImmutable($data['password_reset_expires_at']) : null,
            registrationIp: $data['registration_ip'] ?? '',
            userAgent: $data['user_agent'] ?? '',
            lastLoginAt: $data['last_login_at'] ? new DateTimeImmutable($data['last_login_at']) : null,
            lastLoginIp: $data['last_login_ip'] ?? null,
            lastLoginUserAgent: $data['last_login_user_agent'] ?? null,
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
            deletedAt: $data['deleted_at'] ? new DateTimeImmutable($data['deleted_at']) : null
        );
    }

    /**
     * Convert User to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'trainer_name' => $this->trainerName,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            'email_verified' => $this->emailVerified,
            'email_verification_token' => $this->emailVerificationToken,
            'email_verified_at' => $this->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'password_reset_token' => $this->passwordResetToken,
            'password_reset_expires_at' => $this->passwordResetExpiresAt?->format('Y-m-d H:i:s'),
            'registration_ip' => $this->registrationIp,
            'user_agent' => $this->userAgent,
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'last_login_ip' => $this->lastLoginIp,
            'last_login_user_agent' => $this->lastLoginUserAgent,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s')
        ];
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getTrainerName(): string
    {
        return $this->trainerName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function getEmailVerifiedAt(): ?DateTimeInterface
    {
        return $this->emailVerifiedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function getPasswordResetExpiresAt(): ?DateTimeInterface
    {
        return $this->passwordResetExpiresAt;
    }

    public function getRegistrationIp(): string
    {
        return $this->registrationIp;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function getLastLoginAt(): ?DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function getLastLoginUserAgent(): ?string
    {
        return $this->lastLoginUserAgent;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    // Business Logic Methods

    /**
     * Check if user is pending verification
     */
    public function isPendingVerification(): bool
    {
        return $this->status === 'pending_verification' && !$this->emailVerified;
    }

    /**
     * Check if user is deleted
     */
    public function isDeleted(): bool
    {
        return $this->status === 'deleted' || $this->deletedAt !== null;
    }

    /**
     * Check if password reset token is valid
     */
    public function hasValidPasswordResetToken(): bool
    {
        return $this->passwordResetToken !== null
            && $this->passwordResetExpiresAt !== null
            && $this->passwordResetExpiresAt > new DateTimeImmutable();
    }

    /**
     * Get display name for user
     */
    public function getDisplayName(): string
    {
        return $this->trainerName;
    }

    /**
     * Get user registration age in days
     */
    public function getRegistrationAgeInDays(): int
    {
        $now = new DateTimeImmutable();
        $diff = $now->diff($this->createdAt);
        return (int)$diff->days;
    }

    /**
     * Check if user has logged in recently (within last 30 days)
     */
    public function hasRecentLogin(): bool
    {
        if (!$this->lastLoginAt) {
            return false;
        }

        $thirtyDaysAgo = new DateTimeImmutable('-30 days');
        return $this->lastLoginAt > $thirtyDaysAgo;
    }

    /**
     * Get user's activity status
     */
    public function getActivityStatus(): string
    {
        if (!$this->isActive()) {
            return 'inactive';
        }

        if (!$this->lastLoginAt) {
            return 'never_logged_in';
        }

        $now = new DateTimeImmutable();
        $daysSinceLogin = $now->diff($this->lastLoginAt)->days;

        if ($daysSinceLogin === 0) {
            return 'online_today';
        } elseif ($daysSinceLogin <= 7) {
            return 'active_week';
        } elseif ($daysSinceLogin <= 30) {
            return 'active_month';
        } else {
            return 'inactive_long';
        }
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->emailVerified;
    }
}