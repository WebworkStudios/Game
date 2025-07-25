<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

/**
 * Username Value Object - Validierter Benutzername
 */
readonly class Username
{
    private const int MIN_LENGTH = 3;
    private const int MAX_LENGTH = 50;
    private const string ALLOWED_PATTERN = '/^[a-zA-Z0-9_-]+$/';

    public function __construct(private string $value)
    {
        $this->validate($value);
    }

    public static function fromString(string $username): self
    {
        return new self(trim($username));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(Username $other): bool
    {
        return $this->value === $other->value;
    }

    private function validate(string $value): void
    {
        if (strlen($value) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException('Username too short (min ' . self::MIN_LENGTH . ' characters)');
        }

        if (strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Username too long (max ' . self::MAX_LENGTH . ' characters)');
        }

        if (!preg_match(self::ALLOWED_PATTERN, $value)) {
            throw new \InvalidArgumentException('Username contains invalid characters (only a-z, 0-9, _, - allowed)');
        }

        // Reservierte Namen verbieten
        $reserved = ['admin', 'administrator', 'root', 'system', 'api', 'www', 'mail', 'support'];
        if (in_array(strtolower($value), $reserved, true)) {
            throw new \InvalidArgumentException('Username is reserved');
        }
    }
}