<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\ResetToken;
use PDO;

/**
 * Implementacja repozytorium tokenów resetowania hasła oparta na PDO.
 */
class PdoPasswordResetRepository implements PasswordResetRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
        )->execute([$userId]);
    }

    public function create(int $userId, string $token): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token, expires_at)
             VALUES (?, ?, NOW() + INTERVAL 30 MINUTE)'
        );
        $stmt->execute([$userId, $token]);
    }

    public function findValid(string $token): ?ResetToken
    {
        $stmt = $this->pdo->prepare(
            'SELECT token_id, user_id, token, expires_at, used_at
             FROM password_reset_tokens
             WHERE token = ?
               AND expires_at > NOW()
               AND used_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        return $row ? ResetToken::fromRow($row) : null;
    }

    public function markUsed(int $tokenId): void
    {
        $this->pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE token_id = ?'
        )->execute([$tokenId]);
    }
}
