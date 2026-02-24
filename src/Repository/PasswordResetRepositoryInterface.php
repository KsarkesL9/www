<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\ResetToken;

/**
 * @brief Interface for password reset tokens repository.
 * 
 * Provides methods for managing password reset tokens, generating them, and verifying their validity.
 */
interface PasswordResetRepositoryInterface
{
    /**
     * @brief Begins a database transaction.
     * 
     * Starts a new transaction to ensure data consistency during multiple queries.
     */
    public function beginTransaction(): void;

    /**
     * @brief Commits the current transaction.
     * 
     * Saves all changes made during the current transaction.
     */
    public function commit(): void;

    /**
     * @brief Rolls back the current transaction.
     * 
     * Cancels all changes made during the current transaction in case of an error.
     */
    public function rollBack(): void;

    /**
     * @brief Revokes all active tokens for a user.
     * 
     * Annulls all password reset tokens that are currently active for a specific user.
     * 
     * @param int $userId The ID of the user.
     */
    public function revokeAllForUser(int $userId): void;

    /**
     * @brief Creates a new token.
     * 
     * Stores a generated password reset token in the database for the given user.
     * 
     * @param int $userId The ID of the user.
     * @param string $token The reset token string.
     */
    public function create(int $userId, string $token): void;

    /**
     * @brief Finds a valid token.
     * 
     * Searches for an unused and unexpired password reset token in the database.
     * 
     * @param string $token The reset token string.
     * @return ResetToken|null The token object if valid, or null otherwise.
     */
    public function findValid(string $token): ?ResetToken;

    /**
     * @brief Marks a token as used.
     * 
     * Updates the status of a specific token to indicate that it has already been used.
     * 
     * @param int $tokenId The ID of the token.
     */
    public function markUsed(int $tokenId): void;
}
