<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Domain\User\Entities\UserToken;
use App\Domain\User\Enums\TokenType;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\Repositories\UserTokenRepositoryInterface;
use Framework\Database\QueryBuilder;
use Framework\Database\ConnectionManager;
use Framework\Database\MySQLGrammar;

class UserTokenRepository implements UserTokenRepositoryInterface
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

    public function save(UserToken $token): UserToken
    {
        $data = $token->toArray();

        if ($token->getId() === 0) {
            unset($data['id']);

            $id = $this->queryBuilder
                ->table('user_tokens')
                ->insertGetId($data);

            $token->setId($id);
        } else {
            $id = $data['id'];
            unset($data['id']);

            $this->queryBuilder
                ->table('user_tokens')
                ->where('id', $id)
                ->update($data);
        }

        return $token;
    }

    public function findByToken(string $token): ?UserToken
    {
        $data = $this->queryBuilder
            ->table('user_tokens')
            ->where('token', $token)
            ->first();

        return $data ? UserToken::fromArray($data) : null;
    }

    public function findValidToken(string $token, TokenType $type): ?UserToken
    {
        $data = $this->queryBuilder
            ->table('user_tokens')
            ->where('token', $token)
            ->where('type', $type->value)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->whereNull('used_at')
            ->first();

        return $data ? UserToken::fromArray($data) : null;
    }

    public function deleteExpiredTokens(): int
    {
        return $this->queryBuilder
            ->table('user_tokens')
            ->where('expires_at', '<', date('Y-m-d H:i:s'))
            ->delete();
    }

    public function deleteTokensForUser(UserId $userId, TokenType $type): int
    {
        return $this->queryBuilder
            ->table('user_tokens')
            ->where('user_id', $userId->toInt())
            ->where('type', $type->value)
            ->delete();
    }
}
