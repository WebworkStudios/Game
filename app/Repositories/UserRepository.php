<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Domain\User\Entities\User;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\Username;
use Framework\Database\ConnectionManager;
use Framework\Database\MySQLGrammar;
use Framework\Database\QueryBuilder;
use Framework\Database\Enums\OrderDirection;

/**
 * User Repository - MySQL Implementation
 */
class UserRepository implements UserRepositoryInterface
{
    private QueryBuilder $queryBuilder;

    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {
        $this->queryBuilder = new QueryBuilder(
            connectionManager: $this->connectionManager,
            grammar: new MySQLGrammar()
        );
    }

    // ========================================================================
    // CORE CRUD OPERATIONS
    // ========================================================================

    public function save(User $user): User
    {
        $data = $user->toArray();

        if ($user->getId()->toInt() === 0) {
            // Insert neuer User
            unset($data['id']); // Auto-increment ID

            $id = $this->queryBuilder
                ->table('users')
                ->insertGetId($data);

            $user->setId(UserId::fromInt($id));
        } else {
            // Update existierender User
            $id = $data['id'];
            unset($data['id']);

            $this->queryBuilder
                ->table('users')
                ->where('id', '=', $id)
                ->update($data);
        }

        return $user;
    }

    public function findById(UserId $id): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->where('id', '=', $id->toInt())
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->where('email', '=', $email->toString())
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function findByUsername(Username $username): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->where('username', '=', $username->toString())
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function findByEmailOrUsername(string $identifier): ?User
    {
        // Versuche zuerst Email
        $userByEmail = $this->queryBuilder
            ->table('users')
            ->where('email', '=', $identifier)
            ->first();

        if ($userByEmail) {
            return User::fromArray($userByEmail);
        }

        // Falls nicht gefunden, versuche Username
        $userByUsername = $this->queryBuilder
            ->table('users')
            ->where('username', '=', $identifier)
            ->first();

        return $userByUsername ? User::fromArray($userByUsername) : null;
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->queryBuilder
            ->table('users')
            ->where('email', '=', $email->toString())
            ->exists();
    }

    public function existsByUsername(Username $username): bool
    {
        return $this->queryBuilder
            ->table('users')
            ->where('username', '=', $username->toString())
            ->exists();
    }

    public function findByStatus(UserStatus $status, ?int $limit = null, int $offset = 0): array
    {
        $query = $this->queryBuilder
            ->table('users')
            ->where('status', '=', $status->value)
            ->orderBy('created_at', OrderDirection::DESC)
            ->offset($offset);

        if ($limit !== null) {
            $query->limit($limit);
        }

        $results = $query->get();

        return array_map(fn($data) => User::fromArray($data), $results->toArray());
    }

    public function findByRole(UserRole $role, ?int $limit = null, int $offset = 0): array
    {
        $query = $this->queryBuilder
            ->table('users')
            ->where('role', '=', $role->value)
            ->orderBy('created_at', OrderDirection::DESC)
            ->offset($offset);

        if ($limit !== null) {
            $query->limit($limit);
        }

        $results = $query->get();

        return array_map(fn($data) => User::fromArray($data), $results->toArray());
    }

    public function countByStatus(UserStatus $status): int
    {
        return $this->queryBuilder
            ->table('users')
            ->where('status', '=', $status->value)
            ->count();
    }

    public function countByRole(UserRole $role): int
    {
        return $this->queryBuilder
            ->table('users')
            ->where('role', '=', $role->value)
            ->count();
    }

    public function delete(UserId $id): bool
    {
        $affected = $this->queryBuilder
            ->table('users')
            ->where('id', '=', $id->toInt())
            ->delete();

        return $affected > 0;
    }

    // ========================================================================
    // STATISTICS & ADMIN METHODS
    // ========================================================================

    /**
     * Holt User-Statistiken für Admin-Dashboard
     */
    public function getUserStats(): array
    {
        $totalUsers = $this->getTotalUserCount();
        $activeUsers = $this->getActiveUserCount();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'activity_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0,
        ];
    }

    /**
     * Holt Gesamtanzahl aller User
     */
    public function getTotalUserCount(): int
    {
        return $this->queryBuilder
            ->table('users')
            ->count();
    }

    /**
     * Holt Anzahl aktiver User
     */
    public function getActiveUserCount(): int
    {
        return $this->queryBuilder
            ->table('users')
            ->where('status', '=', UserStatus::ACTIVE->value)
            ->count();
    }

    /**
     * Holt kürzlich registrierte User
     */
    public function getRecentUsers(int $days = 7, int $limit = 10): array
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $results = $this->queryBuilder
            ->table('users')
            ->where('created_at', '>=', $cutoffDate)
            ->orderBy('created_at', OrderDirection::DESC)
            ->limit($limit)
            ->get();

        return array_map(fn($data) => User::fromArray($data), $results->toArray());
    }
}