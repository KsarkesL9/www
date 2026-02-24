<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Encja wiadomości.
 */
class Message
{
    public function __construct(
        private int $messageId,
        private int $threadId,
        private int $senderId,
        private string $content,
        private string $createdAt,
        private ?string $deletedAt = null,
    ) {
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }
    public function getThreadId(): int
    {
        return $this->threadId;
    }
    public function getSenderId(): int
    {
        return $this->senderId;
    }
    public function getContent(): string
    {
        return $this->content;
    }
    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
    public function getDeletedAt(): ?string
    {
        return $this->deletedAt;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            messageId: (int) $row['message_id'],
            threadId: (int) $row['thread_id'],
            senderId: (int) $row['sender_id'],
            content: $row['content'],
            createdAt: $row['created_at'],
            deletedAt: $row['deleted_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'sender_id' => $this->senderId,
            'content' => $this->content,
            'created_at' => $this->createdAt,
        ];
    }
}
