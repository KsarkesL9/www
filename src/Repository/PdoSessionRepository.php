<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Session;
use PDO;

/**
 * Implementacja repozytorium sesji oparta na PDO.
 */
class PdoSessionRepository implements SessionRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $userId, string $token, string $ip, string $userAgent): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, NOW() + INTERVAL 1 HOUR)'
        );
        $stmt->execute([$userId, $token, $ip, $userAgent]);
    }

    public function refresh(string $token): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_sessions SET expires_at = NOW() + INTERVAL 1 HOUR
             WHERE token = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$token]);
    }

    public function revoke(string $token): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_sessions SET revoked_at = NOW() WHERE token = ?'
        );
        $stmt->execute([$token]);
    }

    public function revokeAllForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$userId]);
    }

    public function findActiveByToken(string $token): ?Session
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.session_id, s.user_id, s.token, s.ip_address, s.user_agent,
                    s.expires_at, s.revoked_at,
                    u.status_id, u.role_id, u.first_name, u.surname, r.role_name
             FROM user_sessions s
             JOIN users u ON u.user_id = s.user_id
             JOIN roles r ON r.role_id = u.role_id
             WHERE s.token = ?
               AND s.revoked_at IS NULL
               AND s.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        return $row ? Session::fromRow($row) : null;
    }
}
