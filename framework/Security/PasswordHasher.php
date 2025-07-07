<?php

/**
 * Password Hasher
 * Secure password hashing using Argon2ID
 *
 * File: framework/Security/PasswordHasher.php
 * Directory: /framework/Security/
 */

declare(strict_types=1);

namespace Framework\Security;

class PasswordHasher
{
    private int $algorithm;
    private array $options;

    public function __construct(int|string $algorithm = PASSWORD_ARGON2ID, array $options = [])
    {
        // String zu Int konvertieren falls nötig
        $this->algorithm = match($algorithm) {
            'argon2id', PASSWORD_ARGON2ID => PASSWORD_ARGON2ID,
            'argon2i', PASSWORD_ARGON2I => PASSWORD_ARGON2I,
            'bcrypt', PASSWORD_BCRYPT => PASSWORD_BCRYPT,
            default => is_int($algorithm) ? $algorithm : PASSWORD_ARGON2ID
        };

        $this->options = array_merge([
            'memory_cost' => 65536, // 64MB
            'time_cost' => 4,
            'threads' => 3,
        ], $options);
    }
    /**
     * Hash a password
     */
    public function hash(string $password): string
    {
        $hash = password_hash($password, $this->algorithm, $this->options);

        if ($hash === false) {
            throw new \RuntimeException('Password hashing failed');
        }

        return $hash;
    }

    /**
     * Verify a password against hash
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if hash needs rehashing
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm, $this->options);
    }

    /**
     * Get password strength score
     */
    public function getPasswordStrength(string $password): array
    {
        $score = 0;
        $feedback = [];

        // Length check
        $length = strlen($password);
        if ($length >= 8) {
            $score += 20;
        } else {
            $feedback[] = 'Password should be at least 8 characters long';
        }

        if ($length >= 12) {
            $score += 10;
        }

        // Character variety checks
        if (preg_match('/[a-z]/', $password)) {
            $score += 15;
        } else {
            $feedback[] = 'Password should contain lowercase letters';
        }

        if (preg_match('/[A-Z]/', $password)) {
            $score += 15;
        } else {
            $feedback[] = 'Password should contain uppercase letters';
        }

        if (preg_match('/[0-9]/', $password)) {
            $score += 15;
        } else {
            $feedback[] = 'Password should contain numbers';
        }

        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score += 15;
        } else {
            $feedback[] = 'Password should contain special characters';
        }

        // Bonus for good length
        if ($length >= 16) {
            $score += 10;
        }

        $strength = match (true) {
            $score >= 80 => 'strong',
            $score >= 60 => 'medium',
            $score >= 40 => 'weak',
            default => 'very_weak'
        };

        return [
            'score' => $score,
            'strength' => $strength,
            'feedback' => $feedback
        ];
    }
}