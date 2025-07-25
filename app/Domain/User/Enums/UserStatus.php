<?php
declare(strict_types=1);
namespace App\Domain\User\Enums;

/**
 * User Status Enum - Benutzer-Status
 */
enum UserStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Nicht aktiviert',
            self::ACTIVE => 'Aktiv',
            self::SUSPENDED => 'Gesperrt',
        };
    }

    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }
}