<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * @brief PDO implementation of the thread view repository.
 * 
 * Provides methods for retrieving conversation threads and their participants using a PDO database connection.
 */
class PdoThreadViewRepository implements ThreadViewRepositoryInterface
{
    /**
     * @brief Constructor for the PdoThreadViewRepository.
     * 
     * Initializes the repository with a database connection.
     * 
     * @param PDO $pdo The PDO database connection instance.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @brief Gets user threads.
     * 
     * Retrieves a list of threads for a specific user, along with the latest message and unread count.
     * 
     * @param int $userId The ID of the user.
     * @return array An array containing thread data.
     */
    public function getUserThreads(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                mt.thread_id,
                mt.subject,
                mt.created_at                              AS thread_created_at,
                lm.content                                 AS last_content,
                lm.created_at                              AS last_at,
                lm.sender_id                               AS last_sender_id,
                CONCAT(lu.first_name, ' ', lu.surname)     AS last_sender_name,
                mtp.last_read_at,
                (
                    SELECT COUNT(*)
                    FROM messages m2
                    WHERE m2.thread_id  = mt.thread_id
                      AND m2.sender_id != :uid2
                      AND m2.deleted_at IS NULL
                      AND (mtp.last_read_at IS NULL OR m2.created_at > mtp.last_read_at)
                ) AS unread_count
             FROM message_threads mt
             JOIN message_thread_participants mtp
                  ON mtp.thread_id = mt.thread_id AND mtp.user_id = :uid1
             LEFT JOIN messages lm
                  ON lm.message_id = (
                      SELECT message_id FROM messages
                      WHERE thread_id  = mt.thread_id
                        AND deleted_at IS NULL
                      ORDER BY created_at DESC
                      LIMIT 1
                  )
             LEFT JOIN users lu ON lu.user_id = lm.sender_id
             ORDER BY COALESCE(lm.created_at, mt.created_at) DESC"
        );
        $stmt->execute([':uid1' => $userId, ':uid2' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * @brief Gets participants for a set of threads.
     * 
     * Retrieves connection details between threads and users, omitting a given user.
     * 
     * @param array $threadIds An array of thread identifiers.
     * @param int $excludeUserId The user ID to skip from the results.
     * @return array An array of participants grouped by thread ID.
     */
    public function getParticipantsForThreads(array $threadIds, int $excludeUserId): array
    {
        if (empty($threadIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT mtp.thread_id,
                    CONCAT(u.first_name, ' ', u.surname) AS name,
                    r.role_name
             FROM message_thread_participants mtp
             JOIN users u ON u.user_id  = mtp.user_id
             JOIN roles r ON r.role_id  = u.role_id
             WHERE mtp.thread_id IN ($placeholders)
               AND mtp.user_id != ?
             ORDER BY mtp.thread_id, u.surname"
        );
        $stmt->execute(array_merge($threadIds, [$excludeUserId]));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['thread_id']][] = $row;
        }
        return $result;
    }

    /**
     * @brief Gets a thread if the user is a participant.
     * 
     * Checks if a user belongs to a thread and returns its basic properties.
     * 
     * @param int $threadId The ID of the thread.
     * @param int $userId The ID of the user.
     * @return array|null The thread details array if authorized, or null if denied.
     */
    public function getThreadIfParticipant(int $threadId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mt.thread_id, mt.subject, mt.created_at
             FROM message_threads mt
             JOIN message_thread_participants mtp
                  ON mtp.thread_id = mt.thread_id AND mtp.user_id = ?
             WHERE mt.thread_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $threadId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @brief Gets all messages from a thread.
     * 
     * Retrieves a list of messages within a conversation containing sender identities.
     * 
     * @param int $threadId The ID of the thread.
     * @return array An array of message records.
     */
    public function getThreadMessages(int $threadId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                m.message_id,
                m.sender_id,
                m.content,
                m.created_at,
                m.deleted_at,
                CONCAT(u.first_name, ' ', u.surname) AS sender_name,
                r.role_name AS sender_role
             FROM messages m
             LEFT JOIN users u ON u.user_id = m.sender_id
             LEFT JOIN roles r ON r.role_id  = u.role_id
             WHERE m.thread_id = ?
             ORDER BY m.created_at ASC"
        );
        $stmt->execute([$threadId]);
        return $stmt->fetchAll();
    }

    /**
     * @brief Gets all participants of a thread.
     * 
     * Fetches user data for everyone involved in a given message thread.
     * 
     * @param int $threadId The ID of the thread to interrogate.
     * @return array An array of participant records.
     */
    public function getThreadParticipants(int $threadId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                u.user_id,
                CONCAT(u.first_name, ' ', u.surname) AS name,
                r.role_name,
                mtp.joined_at,
                mtp.last_read_at
             FROM message_thread_participants mtp
             JOIN users u ON u.user_id = mtp.user_id
             JOIN roles r ON r.role_id = u.role_id
             WHERE mtp.thread_id = ?
             ORDER BY u.surname"
        );
        $stmt->execute([$threadId]);
        return $stmt->fetchAll();
    }

    /**
     * @brief Marks a thread as read by user.
     * 
     * Modifies the last_read_at timestamp to indicate a participant saw the latest content.
     * 
     * @param int $threadId The ID of the read thread.
     * @param int $userId The ID of the user who viewed it.
     */
    public function markThreadAsRead(int $threadId, int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE message_thread_participants
             SET last_read_at = NOW()
             WHERE thread_id = ? AND user_id = ?'
        )->execute([$threadId, $userId]);
    }
}
