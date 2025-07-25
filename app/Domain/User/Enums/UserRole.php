<?php
declare(strict_types=1);
namespace App\Domain\User\Enums;

/**
 * User Role Enum - Benutzer-Rollen
 */
enum UserRole: string
{
    case USER = 'user';
    case MODERATOR = 'moderator';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match($this) {
            self::USER => 'Benutzer',
            self::MODERATOR => 'Moderator',
            self::ADMIN => 'Administrator',
        };
    }

    public function canAccessAdmin(): bool
    {
        return in_array($this, [self::ADMIN, self::MODERATOR], true);
    }

    public function canModerateUsers(): bool
    {
        return in_array($this, [self::ADMIN, self::MODERATOR], true);
    }

    public function canManageSystem(): bool
    {
        return $this === self::ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isModerator(): bool
    {
        return $this === self::MODERATOR;
    }

    public function isUser(): bool
    {
        return $this === self::USER;
    }

    /**
     * Hierarchie-Prüfung: Hat diese Rolle mindestens das Level der anderen?
     */
    public function hasLevel(UserRole $requiredRole): bool
    {
        $levels = [
            self::USER->value => 1,
            self::MODERATOR->value => 2,
            self::ADMIN->value => 3,
        ];

        return $levels[$this->value] >= $levels[$requiredRole->value];
    }
}
