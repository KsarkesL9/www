<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * @brief Domain entity that represents an active user session.
 *
 * @details This class holds the data for one row from the
 *          'user_sessions' database table, joined with the
 *          user's first name, surname, and role name.
 *          A session is created when a user logs in and is
 *          identified by a random hex token stored in a cookie.
 */
class Session
{
    /**
     * @brief Creates a new Session object.
     *
     * @param int         $sessionId  The unique ID of this session record.
     * @param int         $userId     The ID of the user who owns this session.
     * @param string      $token      The random hex token stored in the browser cookie.
     * @param string      $ipAddress  The IP address used when the session was created.
     * @param string      $userAgent  The browser user agent string from the login request.
     * @param string      $expiresAt  The date and time when this session ends.
     * @param string|null $revokedAt  The date this session was revoked, or null if active.
     * @param int         $statusId   The numeric status ID of the user's account.
     * @param int         $roleId     The numeric role ID of the user.
     * @param string      $firstName  The user's first name.
     * @param string      $surname    The user's surname.
     * @param string      $roleName   The name of the user's role (e.g. 'student').
     */
    public function __construct(
        private int $sessionId,
        private int $userId,
        private string $token,
        private string $ipAddress,
        private string $userAgent,
        private string $expiresAt,
        private ?string $revokedAt,
        private int $statusId,
        private int $roleId,
        private string $firstName,
        private string $surname,
        private string $roleName,
    ) {
    }

    /**
     * @brief Returns the unique ID of this session record.
     *
     * @return int The session ID from the database.
     */
    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    /**
     * @brief Returns the ID of the user who owns this session.
     *
     * @return int The user ID linked to this session.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @brief Returns the random hex token that identifies this session.
     *
     * @return string The session token string.
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @brief Returns the IP address recorded when the session was created.
     *
     * @return string The IP address as a string.
     */
    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    /**
     * @brief Returns the browser user agent string from the login request.
     *
     * @return string The user agent string.
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @brief Returns the date and time when this session expires.
     *
     * @return string The expiry timestamp as a MySQL datetime string.
     */
    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }

    /**
     * @brief Returns the date and time when this session was revoked.
     *
     * @details Returns null if the session is still active and has not
     *          been revoked. A non-null value means the user logged out
     *          or the session was cancelled for another reason.
     *
     * @return string|null The revocation timestamp, or null if still active.
     */
    public function getRevokedAt(): ?string
    {
        return $this->revokedAt;
    }

    /**
     * @brief Returns the numeric status ID of the user's account.
     *
     * @return int The status ID (e.g. 1 = active, 2 = blocked).
     */
    public function getStatusId(): int
    {
        return $this->statusId;
    }

    /**
     * @brief Returns the numeric role ID of the user.
     *
     * @return int The role ID (e.g. 1 = student, 2 = teacher).
     */
    public function getRoleId(): int
    {
        return $this->roleId;
    }

    /**
     * @brief Returns the first name of the user who owns this session.
     *
     * @return string The user's first name.
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * @brief Returns the surname of the user who owns this session.
     *
     * @return string The user's surname.
     */
    public function getSurname(): string
    {
        return $this->surname;
    }

    /**
     * @brief Returns the name of the role of the user who owns this session.
     *
     * @return string The role name string (e.g. 'student', 'teacher').
     */
    public function getRoleName(): string
    {
        return $this->roleName;
    }

    /**
     * @brief Creates a Session object from a database row array.
     *
     * @details This static factory method takes an associative array
     *          as returned by PDO and maps each key to the correct
     *          constructor parameter. Integer fields are cast to int.
     *          The 'revoked_at' field is optional and defaults to null.
     *
     * @param array $row An associative array from a PDO fetch call.
     *                   Expected keys: session_id, user_id, token,
     *                   ip_address, user_agent, expires_at, revoked_at,
     *                   status_id, role_id, first_name, surname, role_name.
     *
     * @return self A new Session instance filled with data from the row.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            sessionId: (int) $row['session_id'],
            userId: (int) $row['user_id'],
            token: $row['token'],
            ipAddress: $row['ip_address'],
            userAgent: $row['user_agent'],
            expiresAt: $row['expires_at'],
            revokedAt: $row['revoked_at'] ?? null,
            statusId: (int) $row['status_id'],
            roleId: (int) $row['role_id'],
            firstName: $row['first_name'],
            surname: $row['surname'],
            roleName: $row['role_name'],
        );
    }

    /**
     * @brief Converts this session to a plain associative array.
     *
     * @details Returns all session fields as a key-value array.
     *          This method is used for backward compatibility when
     *          page controllers expect a plain array instead of
     *          a Session object.
     *
     * @return array An associative array with all session and user fields.
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'token' => $this->token,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'expires_at' => $this->expiresAt,
            'revoked_at' => $this->revokedAt,
            'status_id' => $this->statusId,
            'role_id' => $this->roleId,
            'first_name' => $this->firstName,
            'surname' => $this->surname,
            'role_name' => $this->roleName,
        ];
    }
}
