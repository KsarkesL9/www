<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Message;

/**
 * @brief Interface for message and thread repository.
 * 
 * Provides methods for managing messages, threads, and their participants.
 */
interface MessageRepositoryInterface
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
     * @brief Creates a new message thread.
     * 
     * This function creates a new conversation thread and returns its ID.
     * 
     * @param string|null $subject The subject of the thread.
     * @return int The ID of the newly created thread.
     */
    public function createThread(?string $subject): int;

    /**
     * @brief Adds a participant to a thread.
     * 
     * Connects a user to a specific conversation thread.
     * 
     * @param int $threadId The ID of the thread.
     * @param int $userId The ID of the user.
     */
    public function addParticipant(int $threadId, int $userId): void;

    /**
     * @brief Checks if a user is a participant of a thread.
     * 
     * Verifies whether a given user belongs to a specific conversation.
     * 
     * @param int $threadId The ID of the thread.
     * @param int $userId The ID of the user.
     * @return bool True if the user is a participant, false otherwise.
     */
    public function isParticipant(int $threadId, int $userId): bool;

    /**
     * @brief Inserts a new message.
     * 
     * Adds a new message to a specific thread and returns its ID.
     * 
     * @param int $threadId The ID of the thread.
     * @param int $senderId The ID of the sender.
     * @param string $content The text content of the message.
     * @return int The ID of the newly inserted message.
     */
    public function insertMessage(int $threadId, int $senderId, string $content): int;

    /**
     * @brief Updates the last read time.
     * 
     * Updates the timestamp when the user last read the thread.
     * 
     * @param int $threadId The ID of the thread.
     * @param int $userId The ID of the user.
     */
    public function updateLastRead(int $threadId, int $userId): void;

    /**
     * @brief Finds a message by its ID.
     * 
     * Searches for a message using its unique identifier.
     * 
     * @param int $messageId The ID of the message.
     * @return Message|null The message object, or null if not found.
     */
    public function findMessageById(int $messageId): ?Message;

    /**
     * @brief Checks if a message belongs to a user.
     * 
     * Verifies if the specified user is the sender of the message and if it is not deleted.
     * 
     * @param int $messageId The ID of the message.
     * @param int $userId The ID of the user.
     * @return bool True if the user owns the message, false otherwise.
     */
    public function isMessageOwnedBy(int $messageId, int $userId): bool;

    /**
     * @brief Soft deletes a message.
     * 
     * Marks a message as deleted without actually removing it from the database.
     * 
     * @param int $messageId The ID of the message to delete.
     */
    public function softDeleteMessage(int $messageId): void;
}
