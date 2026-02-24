<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\User;
use PDO;

/**
 * Implementacja repozytorium użytkowników oparta na PDO.
 */
class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByLogin(string $login): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, s.name AS status_name, r.role_name
             FROM users u
             JOIN statuses s ON s.status_id = u.status_id
             JOIN roles r   ON r.role_id   = u.role_id
             WHERE u.login = ?
             LIMIT 1'
        );
        $stmt->execute([$login]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function findByLoginAndEmail(string $login, string $email): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, s.name AS status_name, r.role_name
             FROM users u
             JOIN statuses s ON s.status_id = u.status_id
             JOIN roles r   ON r.role_id   = u.role_id
             WHERE u.login = ? AND u.email_address = ?
             LIMIT 1'
        );
        $stmt->execute([$login, $email]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function isEmailTaken(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email_address = ?');
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }

    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users
             (role_id, status_id, first_name, second_name, surname, email_address,
              login, password, date_of_birth, country_id, city, street,
              building_number, apartment_number, phone_number, last_password_change)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['role_id'],
            $data['status_id'],
            $data['first_name'],
            $data['second_name'],
            $data['surname'],
            $data['email_address'],
            $data['login'],
            $data['password'],
            $data['date_of_birth'],
            $data['country_id'],
            $data['city'],
            $data['street'],
            $data['building_number'],
            $data['apartment_number'],
            $data['phone_number'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function loginExists(string $login): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE login = ?');
        $stmt->execute([$login]);
        return $stmt->fetchColumn() > 0;
    }

    public function incrementFailedLogins(int $userId): int
    {
        $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE user_id = ?'
        )->execute([$userId]);

        $stmt = $this->pdo->prepare('SELECT failed_login_attempts FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function resetFailedLogins(int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = 0 WHERE user_id = ?'
        )->execute([$userId]);
    }

    public function updateStatus(int $userId, int $statusId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET status_id = ? WHERE user_id = ?');
        $stmt->execute([$statusId, $userId]);
    }

    public function updatePassword(int $userId, string $hashedPassword): void
    {
        $this->pdo->prepare(
            'UPDATE users SET password = ?, last_password_change = NOW(), failed_login_attempts = 0 WHERE user_id = ?'
        )->execute([$hashedPassword, $userId]);
    }

    public function searchActive(string $query, int $limit = 15): array
    {
        $like = '%' . $query . '%';
        $stmt = $this->pdo->prepare(
            "SELECT
                u.user_id,
                CONCAT(u.first_name, ' ', u.surname) AS full_name,
                u.login,
                r.role_name
             FROM users u
             JOIN roles r    ON r.role_id   = u.role_id
             JOIN statuses s ON s.status_id = u.status_id
             WHERE s.name = 'aktywny'
               AND (
                   u.first_name LIKE ?
                   OR u.surname  LIKE ?
                   OR CONCAT(u.first_name, ' ', u.surname) LIKE ?
                   OR u.login    LIKE ?
               )
             ORDER BY u.surname, u.first_name
             LIMIT ?"
        );
        $stmt->execute([$like, $like, $like, $like, $limit]);
        return $stmt->fetchAll();
    }

    public function filterActiveUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT u.user_id
             FROM users u
             JOIN statuses s ON s.status_id = u.status_id
             WHERE u.user_id IN ($placeholders)
               AND s.name = 'aktywny'"
        );
        $stmt->execute($userIds);
        return array_column($stmt->fetchAll(), 'user_id');
    }
}
