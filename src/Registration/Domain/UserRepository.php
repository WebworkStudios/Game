<?php
declare(strict_types=1);

namespace Registration\Domain;

interface UserRepository
{
    public function findByEmail(string $email): ?User;
    public function findByTrainerName(string $trainerName): ?User;
    public function findByVerificationToken(string $token): ?User;
    public function save(User $user): User;
}