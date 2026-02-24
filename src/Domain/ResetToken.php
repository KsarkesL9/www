<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Encja tokenu resetowania hasła.
 */
class ResetToken
{
    public function __construct(
        private int $tokenId,
        private int $userId,
        private string $token,
        private string $expiresAt,
        private ?string $usedAt = null,
    ) {
    }

    public function getTokenId(): int
    {
        return $this->tokenId;
    }
    public function getUserId(): int
    {
        return $this->userId;
    }
    public function getToken(): string
    {
        return $this->token;
    }
    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }
    public function getUsedAt(): ?string
    {
        return $this->usedAt;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            tokenId: (int) $row['token_id'],
            userId: (int) $row['user_id'],
            token: $row['token'] ?? '',
            expiresAt: $row['expires_at'] ?? '',
            usedAt: $row['used_at'] ?? null,
        );
    }
}
