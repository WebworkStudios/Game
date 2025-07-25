<?php
declare(strict_types=1);
namespace App\Domain\User\Entities;

use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Username;
use App\Domain\User\ValueObjects\Password;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Enums\UserRole;

/**
 * User Entity - Rich Domain Model mit Geschäftslogik
 */
class User
{
    private ?\DateTime $emailVerifiedAt = null;
    private ?\DateTime $lastLoginAt = null;
    private int $loginAttempts = 0;
    private ?\DateTime $loginAttemptsResetAt = null;
    private ?\DateTime $usernameLastChangedAt = null;
    private ?string $profileImagePath = null;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;

    public function __construct(
        private UserId $id,
        private Username $username,
        private Email $email,
        private Password $password,
        private UserStatus $status = UserStatus::PENDING,
        private UserRole $role = UserRole::USER,
    ) {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // ========================================================================
    // FACTORY METHODS
    // ========================================================================

    public static function create(
        Username $username,
        Email $email,
        Password $password
    ): self {
        // Neue User haben immer eine temporäre ID, die beim Speichern gesetzt wird
        return new self(
            id: UserId::fromInt(0), // Wird beim Insert gesetzt
            username: $username,
            email: $email,
            password: $password,
            status: UserStatus::PENDING,
            role: UserRole::USER
        );
    }

    public static function fromArray(array $data): self
    {
        $user = new self(
            id: UserId::fromInt($data['id']),
            username: Username::fromString($data['username']),
            email: Email::fromString($data['email']),
            password: Password::fromHash($data['password_hash']),
            status: UserStatus::from($data['status']),
            role: UserRole::from($data['role'])
        );

        // Optional fields
        if (!empty($data['email_verified_at'])) {
            $user->emailVerifiedAt = new \DateTime($data['email_verified_at']);
        }

        if (!empty($data['last_login_at'])) {
            $user->lastLoginAt = new \DateTime($data['last_login_at']);
        }

        if (!empty($data['username_last_changed_at'])) {
            $user->usernameLastChangedAt = new \DateTime($data['username_last_changed_at']);
        }

        $user->loginAttempts = $data['login_attempts'] ?? 0;

        if (!empty($data['login_attempts_reset_at'])) {
            $user->loginAttemptsResetAt = new \DateTime($data['login_attempts_reset_at']);
        }

        $user->profileImagePath = $data['profile_image_path'] ?? null;
        $user->createdAt = new \DateTime($data['created_at']);
        $user->updatedAt = new \DateTime($data['updated_at']);

        return $user;
    }

    public function getId(): UserId { return $this->id; }
    public function getUsername(): Username { return $this->username; }
    public function getEmail(): Email { return $this->email; }
    public function getPassword(): Password { return $this->password; }
    public function getStatus(): UserStatus { return $this->status; }
    public function getRole(): UserRole { return $this->role; }
    public function getEmailVerifiedAt(): ?\DateTime { return $this->emailVerifiedAt; }
    public function getLastLoginAt(): ?\DateTime { return $this->lastLoginAt; }
    public function getLoginAttempts(): int { return $this->loginAttempts; }
    public function getLoginAttemptsResetAt(): ?\DateTime { return $this->loginAttemptsResetAt; }
    public function getUsernameLastChangedAt(): ?\DateTime { return $this->usernameLastChangedAt; }
    public function getProfileImagePath(): ?string { return $this->profileImagePath; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }

    /**
     * Aktiviert User-Account (E-Mail verifiziert)
     */
    public function activate(): void
    {
        if ($this->status->isActive()) {
            throw new \DomainException('User is already active');
        }

        $this->status = UserStatus::ACTIVE;
        $this->emailVerifiedAt = new \DateTime();
        $this->touch();
    }

    /**
     * Sperrt User-Account
     */
    public function suspend(string $reason = ''): void
    {
        if ($this->status->isSuspended()) {
            throw new \DomainException('User is already suspended');
        }

        $this->status = UserStatus::SUSPENDED;
        $this->touch();
    }

    /**
     * Entsperrt User-Account
     */
    public function reactivate(): void
    {
        if (!$this->status->isSuspended()) {
            throw new \DomainException('User is not suspended');
        }

        $this->status = UserStatus::ACTIVE;
        $this->touch();
    }

    /**
     * Prüft ob User sich einloggen kann
     */
    public function canLogin(): bool
    {
        return $this->status->canLogin() && !$this->isLoginBlocked();
    }

    /**
     * Prüft ob Login wegen zu vieler Versuche blockiert ist
     */
    public function isLoginBlocked(): bool
    {
        if ($this->loginAttempts < 5) {
            return false;
        }

        // Login-Sperre läuft nach 30 Minuten ab
        if ($this->loginAttemptsResetAt === null) {
            return true;
        }

        $now = new \DateTime();
        $resetTime = clone $this->loginAttemptsResetAt;
        $resetTime->add(new \DateInterval('PT30M')); // 30 Minuten

        return $now < $resetTime;
    }

    /**
     * Registriert erfolgreichen Login
     */
    public function recordSuccessfulLogin(): void
    {
        $this->lastLoginAt = new \DateTime();
        $this->loginAttempts = 0;
        $this->loginAttemptsResetAt = null;
        $this->touch();
    }

    /**
     * Registriert fehlgeschlagenen Login-Versuch
     */
    public function recordFailedLogin(): void
    {
        $this->loginAttempts++;

        if ($this->loginAttempts >= 5) {
            $this->loginAttemptsResetAt = new \DateTime();
        }

        $this->touch();
    }

    /**
     * Resettet Login-Versuche (z.B. durch Admin)
     */
    public function resetLoginAttempts(): void
    {
        $this->loginAttempts = 0;
        $this->loginAttemptsResetAt = null;
        $this->touch();
    }

    /**
     * Ändert Passwort
     */
    public function changePassword(Password $newPassword): void
    {
        $this->password = $newPassword;
        $this->touch();
    }

    /**
     * Ändert Username (nur alle 90 Tage erlaubt)
     */
    public function changeUsername(Username $newUsername): void
    {
        if ($this->usernameLastChangedAt !== null) {
            $now = new \DateTime();
            $lastChange = clone $this->usernameLastChangedAt;
            $lastChange->add(new \DateInterval('P90D')); // 90 Tage

            if ($now < $lastChange) {
                $daysLeft = $now->diff($lastChange)->days;
                throw new \DomainException("Username can only be changed every 90 days. {$daysLeft} days remaining.");
            }
        }

        $this->username = $newUsername;
        $this->usernameLastChangedAt = new \DateTime();
        $this->touch();
    }

    /**
     * Setzt Profilbild
     */
    public function setProfileImage(string $imagePath): void
    {
        // Validierung der Dateierweiterung
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException('Invalid image format. Allowed: ' . implode(', ', $allowedExtensions));
        }

        $this->profileImagePath = $imagePath;
        $this->touch();
    }

    /**
     * Entfernt Profilbild
     */
    public function removeProfileImage(): void
    {
        $this->profileImagePath = null;
        $this->touch();
    }

    /**
     * Ändert User-Rolle (nur durch Admin)
     */
    public function changeRole(UserRole $newRole): void
    {
        $this->role = $newRole;
        $this->touch();
    }

    /**
     * Prüft ob E-Mail verifiziert ist
     */
    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    /**
     * Prüft ob User Admin-Rechte hat
     */
    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    /**
     * Prüft ob User Moderator-Rechte hat (oder höher)
     */
    public function isModerator(): bool
    {
        return $this->role->hasLevel(UserRole::MODERATOR);
    }

    /**
     * Prüft ob User bestimmte Rolle hat
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Prüft ob User mindestens eine bestimmte Rolle hat
     */
    public function hasMinimumRole(UserRole $minimumRole): bool
    {
        return $this->role->hasLevel($minimumRole);
    }

    /**
     * Konvertiert zu Array für Persistierung
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'username' => $this->username->toString(),
            'email' => $this->email->toString(),
            'password_hash' => $this->password->getHash(),
            'status' => $this->status->value,
            'role' => $this->role->value,
            'profile_image_path' => $this->profileImagePath,
            'username_last_changed_at' => $this->usernameLastChangedAt?->format('Y-m-d H:i:s'),
            'email_verified_at' => $this->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'login_attempts' => $this->loginAttempts,
            'login_attempts_reset_at' => $this->loginAttemptsResetAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Aktualisiert Updated-Timestamp
     */
    private function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Setzt ID nach Database-Insert
     */
    public function setId(UserId $id): void
    {
        if ($this->id->toInt() !== 0) {
            throw new \DomainException('User ID can only be set once');
        }
        $this->id = $id;
    }
}
