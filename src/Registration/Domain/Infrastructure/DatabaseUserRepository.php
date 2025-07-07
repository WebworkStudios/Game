<?php
// src/Registration/Infrastructure/DatabaseUserRepository.php

declare(strict_types=1);

namespace Registration\Domain\Infrastructure;

use Framework\Database\ConnectionPool;
use Registration\Domain\User;
use Registration\Domain\UserRepository;
use Registration\Domain\UserRole;

class DatabaseUserRepository implements UserRepository
{
    private ConnectionPool $db;

    public function __construct(ConnectionPool $db)
    {
        $this->db = $db;
    }

    public function findByEmail(string $email): ?User
    {
        $data = $this->db->table('users')
            ->where('email', strtolower($email))
            ->first();

        return $data ? $this->mapToUser($data) : null;
    }

    public function findByTrainerName(string $trainerName): ?User
    {
        $data = $this->db->table('users')
            ->where('trainer_name', $trainerName)
            ->first();

        return $data ? $this->mapToUser($data) : null;
    }

    public function findByVerificationToken(string $token): ?User
    {
        $data = $this->db->table('users')
            ->where('email_verification_token', $token)
            ->where('email_verification_expires', '>', date('Y-m-d H:i:s'))
            ->where('is_email_verified', false)
            ->first();

        return $data ? $this->mapToUser($data) : null;
    }

    public function save(User $user): User
    {
        if ($user->id === null) {
            return $this->insert($user);
        } else {
            return $this->update($user);
        }
    }

    private function insert(User $user): User
    {
        $id = $this->db->writeTable('users')->insert([
            'trainer_name' => $user->trainerName,
            'email' => $user->email,
            'password_hash' => $user->passwordHash,
            'role' => $user->role->value,
            'action_dollars' => $user->actionDollars,
            'is_email_verified' => $user->isEmailVerified,
            'email_verification_token' => $user->emailVerificationToken,
            'email_verification_expires' => $user->emailVerificationExpires?->format('Y-m-d H:i:s'),
            'newsletter_subscribed' => $user->newsletterSubscribed,
            'created_at' => $user->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $user->updatedAt->format('Y-m-d H:i:s')
        ]);

        return new User(
            id: $id,
            trainerName: $user->trainerName,
            email: $user->email,
            passwordHash: $user->passwordHash,
            role: $user->role,
            actionDollars: $user->actionDollars,
            isEmailVerified: $user->isEmailVerified,
            emailVerificationToken: $user->emailVerificationToken,
            emailVerificationExpires: $user->emailVerificationExpires,
            newsletterSubscribed: $user->newsletterSubscribed,
            createdAt: $user->createdAt,
            updatedAt: $user->updatedAt
        );
    }

    private function update(User $user): User
    {
        $this->db->writeTable('users')
            ->where('id', $user->id)
            ->update([
                'trainer_name' => $user->trainerName,
                'email' => $user->email,
                'password_hash' => $user->passwordHash,
                'role' => $user->role->value,
                'action_dollars' => $user->actionDollars,
                'is_email_verified' => $user->isEmailVerified,
                'email_verification_token' => $user->emailVerificationToken,
                'email_verification_expires' => $user->emailVerificationExpires?->format('Y-m-d H:i:s'),
                'newsletter_subscribed' => $user->newsletterSubscribed,
                'updated_at' => $user->updatedAt->format('Y-m-d H:i:s')
            ]);

        return $user;
    }

    private function mapToUser(array $data): User
    {
        return new User(
            id: (int)$data['id'],
            trainerName: $data['trainer_name'],
            email: $data['email'],
            passwordHash: $data['password_hash'],
            role: UserRole::from($data['role']),
            actionDollars: (float)$data['action_dollars'],
            isEmailVerified: (bool)$data['is_email_verified'],
            emailVerificationToken: $data['email_verification_token'],
            emailVerificationExpires: $data['email_verification_expires']
                ? new \DateTime($data['email_verification_expires'])
                : null,
            newsletterSubscribed: (bool)$data['newsletter_subscribed'],
            createdAt: new \DateTime($data['created_at']),
            updatedAt: new \DateTime($data['updated_at'])
        );
    }
}