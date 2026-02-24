<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Message;
use PDO;

/**
 * @file PdoMessageRepository.php
 * @brief PDO-based implementation of MessageRepositoryInterface.
 *
 * @details Handles all database operations for message threads, participants,
 *          and individual messages. Provides transaction control methods so
 *          the service layer can group multiple operations safely.
 *          No business logic is included here.
 */
class PdoMessageRepository implements MessageRepositoryInterface
{
    /**
     * @brief Creates a new PdoMessageRepository with a PDO connection.
     *
     * @param PDO $pdo An active PDO database connection.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @brief Starts a new database transaction.
     *
     * @details Calls PDO::beginTransaction(). Must be followed by commit()
     *          or rollBack() to end the transaction.
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * @brief Commits the current database transaction.
     *
     * @details Calls PDO::commit() to save all changes made since the last
     *          beginTransaction() call.
     *
     * @return void
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * @brief Rolls back the current database transaction.
     *
     * @details Calls PDO::rollBack() to undo all changes made since the last
     *          beginTransaction() call. Called in the catch block on error.
     *
     * @return void
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * @brief Creates a new message thread row in the database.
     *
     * @details Inserts a row into message_threads with the given subject.
     *          The subject may be null. Returns the auto-incremented thread ID.
     *
     * @param string|null $subject The optional subject line for the thread.
     *
     * @return int The ID of the newly created thread.
     */
    public function createThread(?string $subject): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO message_threads (subject) VALUES (?)');
        $stmt->execute([$subject]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @brief Adds a user as a participant in a message thread.
     *
     * @details Inserts a row into message_thread_participants linking the
     *          given thread_id and user_id. Called once per participant
     *          when a new thread is created.
     *
     * @param int $threadId The ID of the thread to add the participant to.
     * @param int $userId   The ID of the user to add as a participant.
     *
     * @return void
     */
    public function addParticipant(int $threadId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO message_thread_participants (thread_id, user_id) VALUES (?, ?)'
        );
        $stmt->execute([$threadId, $userId]);
    }

    /**
     * @brief Checks if a user is a participant in a given thread.
     *
     * @details Queries message_thread_participants for a row matching both
     *          thread_id and user_id. Returns true if any row is found.
     *
     * @param int $threadId The ID of the thread to check.
     * @param int $userId   The ID of the user to check.
     *
     * @return bool True if the user is a participant, false otherwise.
     */
    public function isParticipant(int $threadId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT thread_id FROM message_thread_participants
             WHERE thread_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$threadId, $userId]);
        return (bool) $stmt->fetch();
    }

    /**
     * @brief Inserts a new message row into the messages table.
     *
     * @details Creates a message linked to the given thread_id and sender_id
     *          with the given text content. Returns the auto-incremented message ID.
     *
     * @param int    $threadId The ID of the thread this message belongs to.
     * @param int    $senderId The ID of the user who is sending the message.
     * @param string $content  The text content of the message.
     *
     * @return int The ID of the newly inserted message.
     */
    public function insertMessage(int $threadId, int $senderId, string $content): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (thread_id, sender_id, content) VALUES (?, ?, ?)'
        );
        $stmt->execute([$threadId, $senderId, $content]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @brief Updates the last_read_at timestamp for a participant in a thread.
     *
     * @details Sets last_read_at = NOW() in message_thread_participants for
     *          the row matching both thread_id and user_id. This marks the
     *          thread as read for the given user.
     *
     * @param int $threadId The ID of the thread to mark as read.
     * @param int $userId   The ID of the user who read the thread.
     *
     * @return void
     */
    public function updateLastRead(int $threadId, int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE message_thread_participants
             SET last_read_at = NOW()
             WHERE thread_id = ? AND user_id = ?'
        )->execute([$threadId, $userId]);
    }

    /**
     * @brief Finds a single message by its ID.
     *
     * @details Queries the messages table for a row with the given message_id.
     *          Returns a Message object built from the row, or null if not found.
     *
     * @param int $messageId The ID of the message to look up.
     *
     * @return Message|null The Message object, or null if the ID does not exist.
     */
    public function findMessageById(int $messageId): ?Message
    {
        $stmt = $this->pdo->prepare(
            'SELECT message_id, thread_id, sender_id, content, created_at, deleted_at
             FROM messages WHERE message_id = ? LIMIT 1'
        );
        $stmt->execute([$messageId]);
        $row = $stmt->fetch();

        return $row ? Message::fromRow($row) : null;
    }

    /**
     * @brief Checks if a message belongs to a user and is not yet deleted.
     *
     * @details Queries the messages table for a row where message_id equals
     *          the given ID, sender_id equals the given user ID, and deleted_at
     *          IS NULL. Returns true only if such a row exists.
     *
     * @param int $messageId The ID of the message to check.
     * @param int $userId    The ID of the user who should own the message.
     *
     * @return bool True if the message belongs to the user and is not deleted.
     */
    public function isMessageOwnedBy(int $messageId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT message_id FROM messages
             WHERE message_id = ? AND sender_id = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$messageId, $userId]);
        return (bool) $stmt->fetch();
    }

    /**
     * @brief Soft-deletes a message by setting its deleted_at timestamp.
     *
     * @details Sets deleted_at = NOW() for the row with the given message_id.
     *          The message row is kept in the database but is treated as
     *          deleted. Other queries should check deleted_at IS NULL to
     *          exclude soft-deleted messages from results.
     *
     * @param int $messageId The ID of the message to soft-delete.
     *
     * @return void
     */
    public function softDeleteMessage(int $messageId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE messages SET deleted_at = NOW() WHERE message_id = ?'
        );
        $stmt->execute([$messageId]);
    }
}
