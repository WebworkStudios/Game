<?php

/**
 * User Repository
 * Data access layer for user management
 *
 * File: src/User/Domain/UserRepository.php
 * Directory: /src/User/Domain/
 */

declare(strict_types=1);

namespace User\Domain;

use Framework\Database\ConnectionPool;

class UserRepository
{
    private ConnectionPool $db;

    public function __construct(ConnectionPool $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new user
     */
    public function create(array $data): int
    {
        return $this->db->writeTable('users')->insert($data);
    }

    /**
     * Find user by ID
     */
    public function findById(int $id): ?User
    {
        $userData = $this->db->table('users')
            ->where('id', $id)
            ->first();

        return $userData ? User::fromArray($userData) : null;
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        $userData = $this->db->table('users')
            ->where('email', $email)
            ->first();

        return $userData ? User::fromArray($userData) : null;
    }

    /**
     * Find user by trainer name
     */
    public function findByTrainerName(string $trainerName): ?User
    {
        $userData = $this->db->table('users')
            ->where('trainer_name', $trainerName)
            ->first();

        return $userData ? User::fromArray($userData) : null;
    }

    /**
     * Find user by email verification token
     */
    public function findByEmailVerificationToken(string $token): ?User
    {
        $userData = $this->db->table('users')
            ->where('email_verification_token', $token)
            ->where('email_verified', false)
            ->first();

        return $userData ? User::fromArray($userData) : null;
    }

    /**
     * Check if email exists
     */
    public function emailExists(string $email): bool
    {
        return $this->db->table('users')
                ->where('email', $email)
                ->count() > 0;
    }

    /**
     * Check if trainer name exists
     */
    public function trainerNameExists(string $trainerName): bool
    {
        return $this->db->table('users')
                ->where('trainer_name', $trainerName)
                ->count() > 0;
    }

    /**
     * Confirm user email
     */
    public function confirmEmail(int $userId): bool
    {
        $affected = $this->db->writeTable('users')
            ->where('id', $userId)
            ->update([
                'email_verified' => true,
                'email_verification_token' => null,
                'status' => 'active',
                'email_verified_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        return $affected > 0;
    }

    /**
     * Update user password
     */
    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $affected = $this->db->writeTable('users')
            ->where('id', $userId)
            ->update([
                'password_hash' => $hashedPassword,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        return $affected > 0;
    }

    /**
     * Update last login
     */
    public function updateLastLogin(int $userId, string $ip, string $userAgent): bool
    {
        $affected = $this->db->writeTable('users')
            ->where('id', $userId)
            ->update([
                'last_login_at' => date('Y-m-d H:i:s'),
                'last_login_ip' => $ip,
                'last_login_user_agent' => $userAgent,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        return $affected > 0;
    }

    /**
     * Set password reset token
     */
    public function setPasswordResetToken(int $userId, string $token): bool
    {
        $affected = $this->db->writeTable('users')
            ->where('id', $userId)
            ->update([
                'password_reset_token' => $token,
                'password_reset_expires_at' => date('Y-m-d H:i:s', time() + 3600), // 1 hour
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        return $affected > 0;
    }

    /**
     * Clear password reset token
     */
    public function clearPasswordResetToken(int $userId): bool
    {
        $affected = $this->db->writeTable('users')
            ->where('id', $userId)
            ->update([
                'password_reset_token' => null,
                'password_reset_expires_at' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        return $affected > 0;
    }

    /**
     * Find user by password reset token
     */
    public function findByPasswordResetToken(string $token): ?User
    {
        $userData = $this->db->table('users')
            ->where('password_reset_token', $token)
            ->where('password_reset_expires_at', '>', date('Y-m-d H:i:s'))
            ->first();

        return $userData ? User::fromArray($userData) : null;
    }

    /**
     * Get user statistics
     */
    public function getStats(): array
    {
        $stats = [];

        // Total users
        $stats['total_users'] = $this->db->table('users')->count();

        // Active users
        $stats['active_users'] = $this->db->table('users')
            ->where('status', 'active')
            ->count();

        // Pending verification
        $stats['pending_verification'] = $this->db->table('users')
            ->where('status', 'pending_verification')
            ->count();

        // Registrations today
        $stats['registrations_today'] = $this->db->table('users')
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->count();

        // Registrations this week
        $stats['registrations_week'] = $this->db->table('users')
            ->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime('-7 days')))
            ->count();

        return $stats;
    }

    /**
     * Get users with pagination
     */
    public function getPaginated(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $query = $this->db->table('users');

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['email_verified'])) {
            $query->where('email_verified', $filters['email_verified']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('trainer_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Get total count
        $total = $query->count();

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $users = $query
            ->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $userObjects = array_map(fn($userData) => User::fromArray($userData), $users);

        return [
            'users' => $userObjects,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Delete user (soft delete)
     */
    public function delete(int $userId): bool
    {
        $affected = $this->db->writeTable('users')
            ->where('id', $userId)
            ->update([
                'status' => 'deleted',
                'deleted_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        return $affected > 0;
    }
}