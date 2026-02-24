<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Implementacja repozytorium widoku wątków oparta na PDO.
 */
class PdoThreadViewRepository implements ThreadViewRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

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

    public function markThreadAsRead(int $threadId, int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE message_thread_participants
             SET last_read_at = NOW()
             WHERE thread_id = ? AND user_id = ?'
        )->execute([$threadId, $userId]);
    }
}
