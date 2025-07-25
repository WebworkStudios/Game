<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

/**
 * User ID Value Object - Typsichere User-ID
 */
readonly class UserId
{
    public function __construct(private int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('User ID must be positive');
        }
    }

    public static function fromString(string $id): self
    {
        return new self((int)$id);
    }

    public static function fromInt(int $id): self
    {
        return new self($id);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function toString(): string
    {
        return (string)$this->value;
    }

    public function equals(UserId $other): bool
    {
        return $this->value === $other->value;
    }
}