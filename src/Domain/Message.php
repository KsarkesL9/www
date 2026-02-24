<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * @brief Domain entity that represents one message in a thread.
 *
 * @details This class holds all the data for a single message row
 *          from the 'messages' database table. It uses PHP 8
 *          constructor property promotion. The class provides
 *          getter methods to read each field, a static factory
 *          method to build an object from a database row array,
 *          and a toArray() method to convert back to a plain array.
 */
class Message
{
    /**
     * @brief Creates a new Message object.
     *
     * @param int         $messageId  The unique ID of the message.
     * @param int         $threadId   The ID of the thread this message belongs to.
     * @param int         $senderId   The ID of the user who sent the message.
     * @param string      $content    The text content of the message.
     * @param string      $createdAt  The date and time when the message was created.
     * @param string|null $deletedAt  The date when the message was soft-deleted, or null.
     */
    public function __construct(
        private int $messageId,
        private int $threadId,
        private int $senderId,
        private string $content,
        private string $createdAt,
        private ?string $deletedAt = null,
    ) {
    }

    /**
     * @brief Returns the unique ID of this message.
     *
     * @return int The message ID from the database.
     */
    public function getMessageId(): int
    {
        return $this->messageId;
    }

    /**
     * @brief Returns the ID of the thread this message belongs to.
     *
     * @return int The thread ID.
     */
    public function getThreadId(): int
    {
        return $this->threadId;
    }

    /**
     * @brief Returns the ID of the user who sent this message.
     *
     * @return int The sender's user ID.
     */
    public function getSenderId(): int
    {
        return $this->senderId;
    }

    /**
     * @brief Returns the text content of this message.
     *
     * @return string The message body text.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @brief Returns the date and time when this message was created.
     *
     * @return string The creation timestamp as a MySQL datetime string.
     */
    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    /**
     * @brief Returns the date and time when this message was soft-deleted.
     *
     * @details Returns null if the message has not been deleted.
     *          A non-null value means the message is marked as deleted
     *          and should be shown as a 'deleted' notice in the UI.
     *
     * @return string|null The deletion timestamp, or null if not deleted.
     */
    public function getDeletedAt(): ?string
    {
        return $this->deletedAt;
    }

    /**
     * @brief Creates a Message object from a database row array.
     *
     * @details This static factory method takes an associative array
     *          as returned by PDO and maps each key to the correct
     *          constructor parameter. Integer fields are cast to int.
     *          The 'deleted_at' field is optional and defaults to null.
     *
     * @param array $row An associative array from a PDO fetch call.
     *                   Expected keys: message_id, thread_id, sender_id,
     *                   content, created_at, deleted_at (optional).
     *
     * @return self A new Message instance filled with data from the row.
     */
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

    /**
     * @brief Converts this message to a plain associative array.
     *
     * @details Returns only the fields needed by the front-end JavaScript.
     *          This method is used for backward compatibility when the
     *          API needs to send message data as JSON.
     *
     * @return array An associative array with keys: message_id, sender_id,
     *               content, created_at.
     */
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
