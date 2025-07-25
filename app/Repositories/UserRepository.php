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
use Framework\Database\Enums\OrderDirection;  // ✅ Import hinzugefügt

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
                ->where('id', '=', $id)  // ✅ Operator hinzugefügt
                ->update($data);
        }

        return $user;
    }

    public function findById(UserId $id): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->where('id', '=', $id->toInt())  // ✅ Operator hinzugefügt
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->where('email', '=', $email->toString())  // ✅ Operator hinzugefügt
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function findByUsername(Username $username): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->where('username', '=', $username->toString())  // ✅ Operator hinzugefügt
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function findByEmailOrUsername(string $identifier): ?User
    {
        // ✅ LÖSUNG 1: Zwei separate Queries kombinieren
        $userByEmail = $this->queryBuilder
            ->table('users')
            ->where('email', '=', $identifier)
            ->first();

        if ($userByEmail) {
            return User::fromArray($userByEmail);
        }

        $userByUsername = $this->queryBuilder
            ->table('users')
            ->where('username', '=', $identifier)
            ->first();

        return $userByUsername ? User::fromArray($userByUsername) : null;
    }

    // ✅ ALTERNATIVE LÖSUNG mit whereRaw (falls OR-Logik gewünscht):
    public function findByEmailOrUsernameAlternative(string $identifier): ?User
    {
        $data = $this->queryBuilder
            ->table('users')
            ->whereRaw('(email = :identifier OR username = :identifier)', [
                'identifier' => $identifier
            ])
            ->first();

        return $data ? User::fromArray($data) : null;
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->queryBuilder
            ->table('users')
            ->where('email', '=', $email->toString())  // ✅ Operator hinzugefügt
            ->exists();
    }

    public function existsByUsername(Username $username): bool
    {
        return $this->queryBuilder
            ->table('users')
            ->where('username', '=', $username->toString())  // ✅ Operator hinzugefügt
            ->exists();
    }

    public function findByStatus(UserStatus $status, ?int $limit = null, int $offset = 0): array
    {
        $query = $this->queryBuilder
            ->table('users')
            ->where('status', '=', $status->value)  // ✅ Operator hinzugefügt
            ->orderBy('created_at', OrderDirection::DESC)  // ✅ Enum statt String
            ->offset($offset);

        if ($limit !== null) {
            $query->limit($limit);
        }

        $results = $query->get();

        // ✅ QueryResult in Array konvertieren
        // Annahme: QueryResult hat eine toArray() oder all() Methode
        return array_map(fn($data) => User::fromArray($data), $results->toArray());
    }

    public function findByRole(UserRole $role, ?int $limit = null, int $offset = 0): array
    {
        $query = $this->queryBuilder
            ->table('users')
            ->where('role', '=', $role->value)  // ✅ Operator hinzugefügt
            ->orderBy('created_at', OrderDirection::DESC)  // ✅ Enum statt String
            ->offset($offset);

        if ($limit !== null) {
            $query->limit($limit);
        }

        $results = $query->get();

        // ✅ QueryResult in Array konvertieren
        return array_map(fn($data) => User::fromArray($data), $results->toArray());
    }

    public function countByStatus(UserStatus $status): int
    {
        return $this->queryBuilder
            ->table('users')
            ->where('status', '=', $status->value)  // ✅ Operator hinzugefügt
            ->count();
    }

    public function countByRole(UserRole $role): int
    {
        return $this->queryBuilder
            ->table('users')
            ->where('role', '=', $role->value)  // ✅ Operator hinzugefügt
            ->count();
    }

    public function delete(UserId $id): bool
    {
        $affected = $this->queryBuilder
            ->table('users')
            ->where('id', '=', $id->toInt())  // ✅ Operator hinzugefügt
            ->delete();

        return $affected > 0;
    }

    /**
     * Admin-Funktionen für Statistiken
     */
    public function getTotalUserCount(): int
    {
        return $this->queryBuilder
            ->table('users')
            ->count();
    }

    public function getActiveUserCount(): int
    {
        return $this->queryBuilder
            ->table('users')
            ->where('status', '=', UserStatus::ACTIVE->value)  // ✅ Operator hinzugefügt
            ->count();
    }

    public function getRecentUsers(int $days = 7, int $limit = 10): array
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $results = $this->queryBuilder
            ->table('users')
            ->where('created_at', '>=', $cutoffDate)  // ✅ Operator hinzugefügt
            ->orderBy('created_at', OrderDirection::DESC)  // ✅ Enum statt String
            ->limit($limit)
            ->get();

        // ✅ QueryResult in Array konvertieren
        return array_map(fn($data) => User::fromArray($data), $results->toArray());
    }

}