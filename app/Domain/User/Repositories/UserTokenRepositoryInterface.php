<?php
declare(strict_types=1);
namespace App\Domain\User\Repositories;

use App\Domain\User\Entities\UserToken;
use App\Domain\User\Enums\TokenType;
use App\Domain\User\ValueObjects\UserId;

interface UserTokenRepositoryInterface
{
    public function save(UserToken $token): UserToken;
    public function findByToken(string $token): ?UserToken;
    public function findValidToken(string $token, TokenType $type): ?UserToken;
    public function deleteExpiredTokens(): int;
    public function deleteTokensForUser(UserId $userId, TokenType $type): int;
}