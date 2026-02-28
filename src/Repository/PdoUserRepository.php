<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\User;
use PDO;

/**
 * @file PdoUserRepository.php
 * @brief PDO-based implementation of UserRepositoryInterface.
 *
 * @details All database queries for user data go through this class.
 *          It uses prepared statements to prevent SQL injection.
 *          Each method either returns a value or modifies a row in the
 *          users table. No business logic is included here.
 */
class PdoUserRepository implements UserRepositoryInterface
{
    /**
     * @brief Creates a new PdoUserRepository with a PDO connection.
     *
     * @param PDO $pdo An active PDO database connection.
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @brief Finds a user by their login name.
     *
     * @details Runs a SELECT query that joins the users table with roles
     *          and statuses to include status_name and role_name columns.
     *          Returns null if no user with that login exists.
     *
     * @param string $login The login name to search for.
     *
     * @return User|null The matching User object, or null if not found.
     */
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

    /**
     * @brief Finds a user by both login name and email address.
     *
     * @details Runs a SELECT query that joins users with roles and statuses.
     *          Both the login and the email_address must match the same row.
     *          This method is used for password reset validation.
     *
     * @param string $login The login name to match.
     * @param string $email The email address to match.
     *
     * @return User|null The matching User object, or null if not found.
     */
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

    /**
     * @brief Checks if the given email address is already used by a user.
     *
     * @details Counts rows in the users table where email_address equals
     *          the given value. Returns true if the count is greater than zero.
     *
     * @param string $email The email address to check.
     *
     * @return bool True if the email is already registered, false otherwise.
     */
    public function isEmailTaken(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email_address = ?');
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * @brief Inserts a new user row into the database.
     *
     * @details Uses a prepared INSERT statement with 15 positional parameters.
     *          The last_password_change column is set to the current time with NOW().
     *          Returns the auto-incremented ID of the new row.
     *
     * @param array $data An associative array of user field values.
     *                    Required keys: role_id, status_id, first_name, second_name,
     *                    surname, email_address, login, password, date_of_birth,
     *                    country_id, city, street, building_number,
     *                    apartment_number, phone_number.
     *
     * @return int The new user's ID (last insert ID).
     */
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

    /**
     * @brief Checks if the given login name already exists in the database.
     *
     * @details Counts rows in the users table where login equals the given value.
     *          Returns true if any row is found.
     *
     * @param string $login The login name to check.
     *
     * @return bool True if the login is taken, false if it is free.
     */
    public function loginExists(string $login): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE login = ?');
        $stmt->execute([$login]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * @brief Increments the failed login counter for a user by one.
     *
     * @details Runs an UPDATE to add 1 to failed_login_attempts, then runs
     *          a SELECT to get the new value. Returns the new counter value.
     *          This is used by AuthService to track failed attempts.
     *
     * @param int $userId The ID of the user whose counter should be increased.
     *
     * @return int The updated value of failed_login_attempts after the increment.
     */
    public function incrementFailedLogins(int $userId): int
    {
        $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE user_id = ?'
        )->execute([$userId]);

        $stmt = $this->pdo->prepare('SELECT failed_login_attempts FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @brief Resets the failed login counter for a user to zero.
     *
     * @details Runs an UPDATE to set failed_login_attempts = 0 for the given
     *          user ID. Called after a successful login.
     *
     * @param int $userId The ID of the user whose counter should be reset.
     *
     * @return void
     */
    public function resetFailedLogins(int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = 0 WHERE user_id = ?'
        )->execute([$userId]);
    }

    /**
     * @brief Updates the status of a user account.
     *
     * @details Sets the status_id column for the given user_id. This is used
     *          to change a user's account state (e.g. from pending to active,
     *          or from active to blocked).
     *
     * @param int $userId   The ID of the user to update.
     * @param int $statusId The new status ID to assign.
     *
     * @return void
     */
    public function updateStatus(int $userId, int $statusId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET status_id = ? WHERE user_id = ?');
        $stmt->execute([$statusId, $userId]);
    }

    /**
     * @brief Updates the hashed password for a user.
     *
     * @details Sets the password column to the new hashed value, updates
     *          last_password_change to NOW(), and resets failed_login_attempts
     *          to 0. All three columns are updated in a single query.
     *
     * @param int    $userId         The ID of the user to update.
     * @param string $hashedPassword The new bcrypt-hashed password string.
     *
     * @return void
     */
    public function updatePassword(int $userId, string $hashedPassword): void
    {
        $this->pdo->prepare(
            'UPDATE users SET password = ?, last_password_change = NOW(), failed_login_attempts = 0 WHERE user_id = ?'
        )->execute([$hashedPassword, $userId]);
    }

    /**
     * @brief Searches for active users whose name or login matches the query.
     *
     * @details Only users with status name 'aktywny' are included in results.
     *          The LIKE pattern is applied to first_name, surname, the combined
     *          full name (CONCAT), and login. Results are ordered by surname
     *          then first_name. The number of rows returned is limited to $limit.
     *
     * @param string $query The search string to match against user fields.
     * @param int    $limit Maximum number of results to return (default 15).
     *
     * @return array A list of matching rows with keys: user_id, full_name,
     *               login, role_name.
     */
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

    /**
     * @brief Filters a list of user IDs to keep only those with active accounts.
     *
     * @details Builds a dynamic IN (...) query using placeholders for the given
     *          array of user IDs. Only IDs whose matching user has status 'aktywny'
     *          are returned. Returns an empty array if the input is empty.
     *
     * @param array $userIds An array of integer user IDs to check.
     *
     * @return array A filtered array of user IDs that belong to active accounts.
     */
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

    /**
     * @brief Gets roles for an array of user IDs.
     * 
     * @param array $userIds An array of integer user IDs.
     * @return array An associative array [user_id => 'role_name'].
     */
    public function getRolesByUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT u.user_id, r.role_name
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE u.user_id IN ($placeholders)"
        );
        $stmt->execute($userIds);

        $roles = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $roles[(int) $row['user_id']] = $row['role_name'];
        }
        return $roles;
    }

    /**
     * @brief Assigns a user to the corresponding role table.
     * 
     * @param int $userId The ID of the user.
     * @param int $roleId The role ID.
     */
    public function assignRoleToUser(int $userId, int $roleId): void
    {
        $tableMap = [
            1 => 'students',
            2 => 'teachers',
            3 => 'parents',
            4 => 'admins'
        ];

        if (!isset($tableMap[$roleId])) {
            return;
        }

        $table = $tableMap[$roleId];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO {$table} (user_id) VALUES (?)");
        $stmt->execute([$userId]);
    }

    /**
     * @brief Gets all allowed message recipients for a given sender.
     * 
     * @param int $senderId The ID of the sender.
     * @param int $senderRoleId The role ID of the sender.
     * @return array List of recipients.
     */
    public function getAllowedMessageRecipients(int $senderId, int $senderRoleId): array
    {
        $roleFilter = '';
        if ($senderRoleId === 1) { // Uczeń
            $roleFilter = 'AND u.role_id IN (2)';
        } elseif ($senderRoleId === 3) { // Rodzic
            $roleFilter = 'AND u.role_id IN (2, 4)';
        }

        $sql = "
            SELECT 
                u.user_id,
                u.role_id,
                r.role_name,
                CASE 
                    WHEN u.role_id = 3 THEN 
                        CONCAT(
                            COALESCE(
                                (SELECT GROUP_CONCAT(CONCAT(s.first_name, ' ', s.surname) SEPARATOR ', ')
                                 FROM students_parents sp
                                 JOIN users s ON sp.student_id = s.user_id
                                 WHERE sp.parent_id = u.user_id),
                                CONCAT(u.first_name, ' ', u.surname)
                            ),
                            ' (rodzic)'
                        )
                    ELSE CONCAT(u.first_name, ' ', u.surname)
                END as full_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.status_id = (SELECT status_id FROM statuses WHERE name = 'aktywny')
              AND u.user_id != :sender_id
              $roleFilter
            ORDER BY u.role_id, u.surname, u.first_name
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sender_id' => $senderId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
