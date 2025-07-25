<?php
declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Username;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Enums\UserRole;

/**
 * User Repository Interface - Persistierung-Abstraktion
 */
interface UserRepositoryInterface
{
    // ========================================================================
    // CORE CRUD OPERATIONS
    // ========================================================================

    public function save(User $user): User;

    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function findByUsername(Username $username): ?User;

    public function findByEmailOrUsername(string $identifier): ?User;

    public function existsByEmail(Email $email): bool;

    public function existsByUsername(Username $username): bool;

    public function findByStatus(UserStatus $status, ?int $limit = null, int $offset = 0): array;

    public function findByRole(UserRole $role, ?int $limit = null, int $offset = 0): array;

    public function countByStatus(UserStatus $status): int;

    public function countByRole(UserRole $role): int;

    public function delete(UserId $id): bool;

    // ========================================================================
    // STATISTICS & ADMIN METHODS
    // ========================================================================

    /**
     * Holt User-Statistiken für Admin-Dashboard
     */
    public function getUserStats(): array;

    /**
     * Holt Gesamtanzahl aller User
     */
    public function getTotalUserCount(): int;

    /**
     * Holt Anzahl aktiver User
     */
    public function getActiveUserCount(): int;

    /**
     * Holt kürzlich registrierte User
     */
    public function getRecentUsers(int $days = 7, int $limit = 10): array;
}