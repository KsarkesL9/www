<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\User;

/**
 * @brief Interface for user repository.
 * 
 * Provides methods for managing user data, searching, authentication checks, and modifying user state.
 */
interface UserRepositoryInterface
{
    /**
     * @brief Finds a user by their login.
     * 
     * This function retrieves a user based on their unique login name.
     * 
     * @param string $login The login name of the user.
     * @return User|null The user object if found, or null otherwise.
     */
    public function findByLogin(string $login): ?User;

    /**
     * @brief Finds a user by their login and email address.
     * 
     * Searches for a user matching both the specific login and email address.
     * 
     * @param string $login The login name.
     * @param string $email The email address.
     * @return User|null The user object if found, or null otherwise.
     */
    public function findByLoginAndEmail(string $login, string $email): ?User;

    /**
     * @brief Checks if an email is already taken.
     * 
     * Verifies whether the specified email address is already associated with an existing account.
     * 
     * @param string $email The email address to check.
     * @return bool True if the email is taken, false otherwise.
     */
    public function isEmailTaken(string $email): bool;

    /**
     * @brief Inserts a new user.
     * 
     * Adds a new user record to the database and returns the new user's ID.
     * 
     * @param array $data An array containing the user's data (e.g., login, email, password, etc.).
     * @return int The ID of the newly created user.
     */
    public function insert(array $data): int;

    /**
     * @brief Checks if a login exists.
     * 
     * Verifies if the specified login name is already in use by another account.
     * 
     * @param string $login The login name to check.
     * @return bool True if the login exists, false otherwise.
     */
    public function loginExists(string $login): bool;

    /**
     * @brief Increments the counter for failed logins.
     * 
     * Adds 1 to the user's failed login attempts counter.
     * 
     * @param int $userId The ID of the user.
     * @return int The new total of failed login attempts.
     */
    public function incrementFailedLogins(int $userId): int;

    /**
     * @brief Resets the counter for failed logins.
     * 
     * Sets the failed login attempts counter back to zero for the specified user.
     * 
     * @param int $userId The ID of the user.
     */
    public function resetFailedLogins(int $userId): void;

    /**
     * @brief Updates the status of a user.
     * 
     * Changes the account status (e.g., active, suspended, locked) for the given user.
     * 
     * @param int $userId The ID of the user.
     * @param int $statusId The new status ID.
     */
    public function updateStatus(int $userId, int $statusId): void;

    /**
     * @brief Updates the user's password.
     * 
     * Sets a new hashed password for the user and resets any failed login attempts.
     * 
     * @param int $userId The ID of the user.
     * @param string $hashedPassword The new, securely hashed password.
     */
    public function updatePassword(int $userId, string $hashedPassword): void;

    /**
     * @brief Searches for active users.
     * 
     * Finds active users whose names or logins match the search query. It returns a limited number of results.
     * 
     * @param string $query The search keyword or phrase.
     * @param int $limit The maximum number of users to return. Default is 15.
     * @return array An array of matching active users.
     */
    public function searchActive(string $query, int $limit = 15): array;

    /**
     * @brief Filters a list of user IDs to keep only the active ones.
     * 
     * Given an array of user IDs, this function checks the database and returns only the IDs of those who are currently active.
     * 
     * @param array $userIds An array of user IDs to check.
     * @return array An array containing only the IDs of active users.
     */
    public function filterActiveUserIds(array $userIds): array;
    /**
     * @brief Gets roles for an array of user IDs.
     * 
     * @param array $userIds An array of integer user IDs.
     * @return array An associative array [user_id => 'role_name'].
     */
    public function getRolesByUserIds(array $userIds): array;

    /**
     * @brief Assigns a user to the corresponding role table.
     * 
     * @param int $userId The ID of the user.
     * @param int $roleId The role ID.
     */
    public function assignRoleToUser(int $userId, int $roleId): void;

    /**
     * @brief Gets all allowed message recipients for a given sender.
     * 
     * @param int $senderId The ID of the sender.
     * @param int $senderRoleId The role ID of the sender.
     * @return array List of recipients.
     */
    public function getAllowedMessageRecipients(int $senderId, int $senderRoleId): array;
}
