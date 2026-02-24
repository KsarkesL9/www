<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Session;

/**
 * @brief Interface for session repository.
 * 
 * Provides methods for managing user sessions.
 */
interface SessionRepositoryInterface
{
    /**
     * @brief Creates a new session.
     * 
     * This function creates a new session in the database for a user and stores their device details.
     * 
     * @param int $userId The ID of the user.
     * @param string $token The unique session token.
     * @param string $ip The IP address from which the session is created.
     * @param string $userAgent The user agent string of the device.
     */
    public function create(int $userId, string $token, string $ip, string $userAgent): void;

    /**
     * @brief Refreshes a session.
     * 
     * Updates the expiration time of an active session to keep the user logged in.
     * 
     * @param string $token The session token string.
     */
    public function refresh(string $token): void;

    /**
     * @brief Revokes a session.
     * 
     * Terminates a specific session by its token so it can no longer be used.
     * 
     * @param string $token The session token string.
     */
    public function revoke(string $token): void;

    /**
     * @brief Revokes all sessions for a user.
     * 
     * This function logs the user out from all devices by destroying all their sessions.
     * 
     * @param int $userId The ID of the user.
     */
    public function revokeAllForUser(int $userId): void;

    /**
     * @brief Finds an active session by token.
     * 
     * Searches for a valid, unexpired session using its token. If found, it returns session details along with user data.
     * 
     * @param string $token The session token string.
     * @return Session|null The active session object, or null if invalid or expired.
     */
    public function findActiveByToken(string $token): ?Session;
}
