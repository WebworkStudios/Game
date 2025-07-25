<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

/**
 * Email Value Object - Validierte E-Mail-Adresse
 */
readonly class Email
{
    public function __construct(private string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        if (strlen($value) > 255) {
            throw new \InvalidArgumentException('Email too long (max 255 characters)');
        }
    }

    public static function fromString(string $email): self
    {
        return new self(strtolower(trim($email)));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }

    public function getLocalPart(): string
    {
        return substr($this->value, 0, strpos($this->value, '@'));
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }
}