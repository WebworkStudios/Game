<?php
declare(strict_types=1);
namespace App\Domain\User\Entities;

use App\Domain\User\Enums\TokenType;
use App\Domain\User\ValueObjects\UserId;

/**
 * User Token Entity - E-Mail-Verifikation und Password-Reset
 */
class UserToken
{
    public function __construct(
        private int $id,
        private UserId $userId,
        private string $token,
        private TokenType $type,
        private \DateTime $expiresAt,
        private ?\DateTime $usedAt = null,
        private \DateTime $createdAt = new \DateTime()
    ) {}

    public static function createEmailVerification(UserId $userId): self
    {
        return new self(
            id: 0,
            userId: $userId,
            token: self::generateSecureToken(),
            type: TokenType::EMAIL_VERIFICATION,
            expiresAt: new \DateTime('+24 hours'),
            createdAt: new \DateTime()
        );
    }

    public static function createPasswordReset(UserId $userId): self
    {
        return new self(
            id: 0,
            userId: $userId,
            token: self::generateSecureToken(),
            type: TokenType::PASSWORD_RESET,
            expiresAt: new \DateTime('+24 hours'),
            createdAt: new \DateTime()
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            userId: UserId::fromInt($data['user_id']),
            token: $data['token'],
            type: TokenType::from($data['type']),
            expiresAt: new \DateTime($data['expires_at']),
            usedAt: !empty($data['used_at']) ? new \DateTime($data['used_at']) : null,
            createdAt: new \DateTime($data['created_at'])
        );
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getUserId(): UserId { return $this->userId; }
    public function getToken(): string { return $this->token; }
    public function getType(): TokenType { return $this->type; }
    public function getExpiresAt(): \DateTime { return $this->expiresAt; }
    public function getUsedAt(): ?\DateTime { return $this->usedAt; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }

    /**
     * Prüft ob Token gültig ist
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    /**
     * Prüft ob Token abgelaufen ist
     */
    public function isExpired(): bool
    {
        return new \DateTime() > $this->expiresAt;
    }

    /**
     * Prüft ob Token bereits verwendet wurde
     */
    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    /**
     * Markiert Token als verwendet
     */
    public function markAsUsed(): void
    {
        if ($this->isUsed()) {
            throw new \DomainException('Token is already used');
        }

        if ($this->isExpired()) {
            throw new \DomainException('Token is expired');
        }

        $this->usedAt = new \DateTime();
    }

    /**
     * Generiert sicheren Token
     */
    private static function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 Zeichen hex
    }

    /**
     * Konvertiert zu Array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId->toInt(),
            'token' => $this->token,
            'type' => $this->type->value,
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
            'used_at' => $this->usedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    public function setId(int $id): void
    {
        if ($this->id !== 0) {
            throw new \DomainException('Token ID can only be set once');
        }
        $this->id = $id;
    }
}