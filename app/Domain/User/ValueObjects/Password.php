<?php
declare(strict_types=1);
namespace App\Domain\User\ValueObjects;

/**
 * Password Value Object - Sichere Passwort-Handhabung
 */
readonly class Password
{
    private const int MIN_LENGTH = 8;
    private const int MAX_LENGTH = 128;

    public function __construct(private string $hashedValue)
    {
        // Akzeptiert nur bereits gehashte Passwörter
        if (empty($hashedValue)) {
            throw new \InvalidArgumentException('Password hash cannot be empty');
        }
    }

    public static function fromPlaintext(string $plaintext): self
    {
        self::validatePlaintext($plaintext);

        $hash = password_hash($plaintext, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);

        if ($hash === false) {
            throw new \RuntimeException('Failed to hash password');
        }

        return new self($hash);
    }

    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    public function getHash(): string
    {
        return $this->hashedValue;
    }

    public function verify(string $plaintext): bool
    {
        return password_verify($plaintext, $this->hashedValue);
    }

    public function needsRehash(): bool
    {
        return password_needs_rehash($this->hashedValue, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);
    }

    private static function validatePlaintext(string $plaintext): void
    {
        if (strlen($plaintext) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException('Password too short (min ' . self::MIN_LENGTH . ' characters)');
        }

        if (strlen($plaintext) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Password too long (max ' . self::MAX_LENGTH . ' characters)');
        }

        // Passwort-Stärke prüfen
        $score = 0;

        if (preg_match('/[a-z]/', $plaintext)) $score++; // Kleinbuchstaben
        if (preg_match('/[A-Z]/', $plaintext)) $score++; // Großbuchstaben
        if (preg_match('/[0-9]/', $plaintext)) $score++; // Zahlen
        if (preg_match('/[^a-zA-Z0-9]/', $plaintext)) $score++; // Sonderzeichen

        if ($score < 3) {
            throw new \InvalidArgumentException('Password too weak (needs at least 3 of: lowercase, uppercase, numbers, special characters)');
        }

        // Häufige Passwörter verbieten
        $commonPasswords = [
            'password', '12345678', 'qwertz123', 'admin123', 'password123',
            'kickerscup', 'football', 'fussball', 'manager123'
        ];

        if (in_array(strtolower($plaintext), $commonPasswords, true)) {
            throw new \InvalidArgumentException('Password is too common');
        }
    }
}