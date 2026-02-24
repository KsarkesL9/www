<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Message;
use PDO;

/**
 * Implementacja repozytorium wiadomości oparta na PDO.
 */
class PdoMessageRepository implements MessageRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createThread(?string $subject): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO message_threads (subject) VALUES (?)');
        $stmt->execute([$subject]);
        return (int) $this->pdo->lastInsertId();
    }

    public function addParticipant(int $threadId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO message_thread_participants (thread_id, user_id) VALUES (?, ?)'
        );
        $stmt->execute([$threadId, $userId]);
    }

    public function isParticipant(int $threadId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT thread_id FROM message_thread_participants
             WHERE thread_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$threadId, $userId]);
        return (bool) $stmt->fetch();
    }

    public function insertMessage(int $threadId, int $senderId, string $content): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (thread_id, sender_id, content) VALUES (?, ?, ?)'
        );
        $stmt->execute([$threadId, $senderId, $content]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateLastRead(int $threadId, int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE message_thread_participants
             SET last_read_at = NOW()
             WHERE thread_id = ? AND user_id = ?'
        )->execute([$threadId, $userId]);
    }

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

    public function softDeleteMessage(int $messageId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE messages SET deleted_at = NOW() WHERE message_id = ?'
        );
        $stmt->execute([$messageId]);
    }

    /**
     * Zwraca obiekt PDO — potrzebne do transakcji w serwisie.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
