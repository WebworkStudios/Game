<?php

/**
 * Rate Limiter
 * Request rate limiting for security and performance
 *
 * File: framework/Security/RateLimiter.php
 * Directory: /framework/Security/
 */

declare(strict_types=1);

namespace Framework\Security;

use Framework\Database\ConnectionPool;

class RateLimiter
{
    private ConnectionPool $db;

    public function __construct(ConnectionPool $db)
    {
        $this->db = $db;
    }

    /**
     * Check if request is allowed
     */
    public function allowRequest(int $maxAttempts, string $action = 'default', int $windowSeconds = 3600): bool
    {
        $identifier = $this->getIdentifier();

        $this->cleanExpiredEntries();

        $currentAttempts = $this->getCurrentAttempts($identifier, $action);

        if ($currentAttempts >= $maxAttempts) {
            return false;
        }

        $this->recordAttempt($identifier, $action, $windowSeconds);

        return true;
    }

    /**
     * Get client identifier
     */
    private function getIdentifier(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Use X-Forwarded-For if behind proxy
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($forwardedIps[0]);
        }

        return $ip;
    }

    /**
     * Clean expired entries
     */
    private function cleanExpiredEntries(): void
    {
        $this->db->writeTable('rate_limits')
            ->where('reset_time', '<=', date('Y-m-d H:i:s'))
            ->delete();
    }

    /**
     * Get current attempt count
     */
    public function getCurrentAttempts(string $identifier, string $action): int
    {
        $result = $this->db->table('rate_limits')
            ->where('identifier', $identifier)
            ->where('action', $action)
            ->where('reset_time', '>', date('Y-m-d H:i:s'))
            ->first();

        return $result ? (int)$result['attempts'] : 0;
    }

    /**
     * Record an attempt
     */
    private function recordAttempt(string $identifier, string $action, int $windowSeconds): void
    {
        $resetTime = date('Y-m-d H:i:s', time() + $windowSeconds);

        $existing = $this->db->table('rate_limits')
            ->where('identifier', $identifier)
            ->where('action', $action)
            ->where('reset_time', '>', date('Y-m-d H:i:s'))
            ->first();

        if ($existing) {
            $this->db->writeTable('rate_limits')
                ->where('id', $existing['id'])
                ->update([
                    'attempts' => $existing['attempts'] + 1,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            $this->db->writeTable('rate_limits')->insert([
                'identifier' => $identifier,
                'action' => $action,
                'attempts' => 1,
                'reset_time' => $resetTime,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Get time until reset
     */
    public function getTimeUntilReset(string $identifier, string $action): int
    {
        $result = $this->db->table('rate_limits')
            ->where('identifier', $identifier)
            ->where('action', $action)
            ->where('reset_time', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$result) {
            return 0;
        }

        $resetTime = strtotime($result['reset_time']);
        return max(0, $resetTime - time());
    }

    /**
     * Reset rate limit for identifier and action
     */
    public function reset(string $identifier, string $action): void
    {
        $this->db->writeTable('rate_limits')
            ->where('identifier', $identifier)
            ->where('action', $action)
            ->delete();
    }

    /**
     * Reset all rate limits for identifier
     */
    public function resetAll(string $identifier): void
    {
        $this->db->writeTable('rate_limits')
            ->where('identifier', $identifier)
            ->delete();
    }
}