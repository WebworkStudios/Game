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
                ->where('id', $id)
                ->update($data);
        }

        return $user;
    }

    public function findById(UserId $id): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->where('id', $id->toInt())
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->where('email', $email->toString())
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function findByUsername(Username $username): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->where('username', $username->toString())
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function findByEmailOrUsername(string $identifier): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->where(function($query) use ($identifier) {
                $query->where('email', $identifier)
                    ->orWhere('username', $identifier);
            })
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->queryBuilder
            ->table('users')
            ->where('email', $email->toString())
            ->exists();
    }

    public function existsByUsername(Username $username): bool
    {
        return $this->queryBuilder
            ->table('users')
            ->where('username', $username->toString())
            ->exists();
    }

    public function findByStatus(UserStatus $status, ?int $limit = null, int $offset = 0): array
    {
        $query = $this->queryBuilder
            ->table('users')
            ->where('status', $status->value)
            ->orderBy('created_at', 'DESC')
            ->offset($offset);

        if ($limit !== null) {
            $query->limit($limit);
        }

        $results = $query->get();

        return array_map(fn($data) => User::fromArray($data), $results);
    }

    public function findByRole(UserRole $role, ?int $limit = null, int $offset = 0): array
    {
        $query = $this->queryBuilder
            ->table('users')
            ->where('role', $role->value)
            ->orderBy('created_at', 'DESC')
            ->offset($offset);

        if ($limit !== null) {
            $query->limit($limit);
        }

        $results = $query->get();

        return array_map(fn($data) => User::fromArray($data), $results);
    }

    public function countByStatus(UserStatus $status): int
    {
        return $this->queryBuilder
            ->table('users')
            ->where('status', $status->value)
            ->count();
    }

    public function countByRole(UserRole $role): int
    {
        return $this->queryBuilder
            ->table('users')
            ->where('role', $role->value)
            ->count();
    }

    public function delete(UserId $id): bool
    {
        $affected = $this->queryBuilder
            ->table('users')
            ->where('id', $id->toInt())
            ->delete();

        return $affected > 0;
    }

    /**
     * Admin-Funktionen für Statistiken
     */
    public function getUserStats(): array
    {
        $statusStats = $this->queryBuilder
            ->table('users')
            ->select(['status', 'COUNT(*) as count'])
            ->groupBy('status')
            ->get();

        $roleStats = $this->queryBuilder
            ->table('users')
            ->select(['role', 'COUNT(*) as count'])
            ->groupBy('role')
            ->get();

        return [
            'total' => $this->queryBuilder->table('users')->count(),
            'by_status' => array_column($statusStats, 'count', 'status'),
            'by_role' => array_column($roleStats, 'count', 'role'),
        ];
    }

    /**
     * Findet User mit zu vielen Login-Versuchen
     */
    public function findBlockedUsers(): array
    {
        $results = $this->queryBuilder
            ->table('users')
            ->where('login_attempts', '>=', 5)
            ->orderBy('login_attempts_reset_at', 'DESC')
            ->get();

        return array_map(fn($data) => User::fromArray($data), $results);
    }
}