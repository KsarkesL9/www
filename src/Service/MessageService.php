<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\MessageRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * @brief Service class for creating, sending, and deleting messages.
 *
 * @details This service handles all message-related business logic:
 *          creating new threads, sending replies, soft-deleting messages,
 *          and searching for users. Database transactions are managed by
 *          the message repository. The class contains no SQL, no PDO,
 *          and no HTTP code.
 */
class MessageService
{
    /**
     * @brief Creates a new MessageService with the required repositories.
     *
     * @param MessageRepositoryInterface $messageRepo Repository for message and thread operations.
     * @param UserRepositoryInterface    $userRepo    Repository for user lookup and validation.
     */
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepo,
        private readonly UserRepositoryInterface $userRepo,
    ) {
    }

    /**
     * @brief Creates a new message thread and sends the first message.
     *
     * @details Validates that the content is not empty and that the
     *          recipient list is not empty. Sanitizes and deduplicates
     *          recipient IDs and removes the sender's own ID from the list.
     *          Checks that at least one recipient has an active account.
     *          Then runs a database transaction to: create the thread row,
     *          add all participants (sender + valid recipients), insert
     *          the first message, and update the sender's last-read time.
     *          If any step fails, the transaction is rolled back.
     *
     * @param int         $senderId      The ID of the user sending the message.
     * @param array       $recipientIds  An array of user IDs to add as recipients.
     * @param string      $content       The text content of the first message.
     * @param string|null $subject       The optional subject line for the thread.
     *
     * @return array{success: bool, message: string, thread_id?: int, message_id?: int}
     *         On success: success=true with thread_id and message_id.
     *         On failure: success=false with an error message string.
     */
    public function createThread(int $senderId, array $recipientIds, string $content, ?string $subject = null): array
    {
        if (empty($content)) {
            return ['success' => false, 'message' => 'Treść wiadomości jest wymagana.'];
        }

        if (empty($recipientIds) || !is_array($recipientIds)) {
            return ['success' => false, 'message' => 'Podaj co najmniej jednego odbiorcę.'];
        }

        // Sanitize
        $recipientIds = array_values(array_unique(array_map('intval', $recipientIds)));
        $recipientIds = array_filter($recipientIds, fn($id) => $id > 0 && $id !== $senderId);

        if (empty($recipientIds)) {
            return ['success' => false, 'message' => 'Nieprawidłowi odbiorcy.'];
        }

        // Verify that recipients have active accounts
        $validRecipients = $this->userRepo->filterActiveUserIds($recipientIds);
        if (empty($validRecipients)) {
            return ['success' => false, 'message' => 'Żaden z podanych odbiorców nie ma aktywnego konta.'];
        }

        // --- NEW LOGIC: Prevent sending messages to administrators if the sender is a student ---
        $roles = $this->userRepo->getRolesByUserIds(array_merge([$senderId], $validRecipients));
        $senderRole = $roles[$senderId] ?? null;

        if ($senderRole === 'uczeń') {
            foreach ($validRecipients as $recId) {
                if (($roles[$recId] ?? null) === 'administrator') {
                    return ['success' => false, 'message' => 'Nie masz uprawnień do wysłania wiadomości do administratora.'];
                }
            }
        }

        $this->messageRepo->beginTransaction();
        try {
            $threadId = $this->messageRepo->createThread($subject ?: null);

            // Add all participants
            $allParticipants = array_unique(array_merge([$senderId], $validRecipients));
            foreach ($allParticipants as $participantId) {
                $this->messageRepo->addParticipant($threadId, (int) $participantId);
            }

            // Insert the first message
            $messageId = $this->messageRepo->insertMessage($threadId, $senderId, $content);
            $this->messageRepo->updateLastRead($threadId, $senderId);

            $this->messageRepo->commit();

            return [
                'success' => true,
                'message' => 'Wiadomość wysłana.',
                'thread_id' => $threadId,
                'message_id' => $messageId,
            ];
        } catch (\Exception $e) {
            $this->messageRepo->rollBack();
            return ['success' => false, 'message' => 'Błąd podczas wysyłania wiadomości. Spróbuj ponownie.'];
        }
    }

    /**
     * @brief Sends a reply message in an existing thread.
     *
     * @details Validates that the thread ID is not zero and the content is
     *          not empty. Checks that the sender is a participant in the
     *          given thread (returns HTTP 403 status if not). Then runs a
     *          transaction to insert the message and update the sender's
     *          last-read timestamp. Loads the saved message and returns it.
     *          If any step fails, the transaction is rolled back.
     *
     * @param int    $senderId  The ID of the user sending the reply.
     * @param int    $threadId  The ID of the thread to send the reply into.
     * @param string $content   The text content of the reply message.
     *
     * @return array{success: bool, message: string, data?: array|null, status?: int}
     *         On success: success=true with a 'data' key containing the message array.
     *         On failure: success=false with an error message. May include 'status' = 403.
     */
    public function sendMessage(int $senderId, int $threadId, string $content): array
    {
        if (!$threadId) {
            return ['success' => false, 'message' => 'Nieprawidłowy wątek.'];
        }

        if (empty($content)) {
            return ['success' => false, 'message' => 'Treść wiadomości jest wymagana.'];
        }

        if (!$this->messageRepo->isParticipant($threadId, $senderId)) {
            return ['success' => false, 'message' => 'Nie jesteś uczestnikiem tego wątku.', 'status' => 403];
        }

        $this->messageRepo->beginTransaction();
        try {
            $messageId = $this->messageRepo->insertMessage($threadId, $senderId, $content);
            $this->messageRepo->updateLastRead($threadId, $senderId);

            $this->messageRepo->commit();

            $msg = $this->messageRepo->findMessageById($messageId);

            return [
                'success' => true,
                'message' => 'Wiadomość wysłana.',
                'data' => $msg ? $msg->toArray() : null,
            ];
        } catch (\Exception $e) {
            $this->messageRepo->rollBack();
            return ['success' => false, 'message' => 'Błąd podczas wysyłania wiadomości. Spróbuj ponownie.'];
        }
    }

    /**
     * @brief Soft-deletes a message by setting its deleted_at timestamp.
     *
     * @details Validates that the message ID is not zero. Checks that the
     *          message belongs to the given user and is not already deleted.
     *          If the check fails, returns an error with HTTP status 403.
     *          If the check passes, calls softDeleteMessage() on the repository
     *          which sets the 'deleted_at' column to the current time.
     *          The message is not removed from the database; it is just marked
     *          as deleted and shown as a notice to all participants.
     *
     * @param int $userId     The ID of the user requesting the deletion.
     * @param int $messageId  The ID of the message to soft-delete.
     *
     * @return array{success: bool, message: string, status?: int}
     *         On success: success=true with a confirmation message.
     *         On failure: success=false with an error. May include 'status' = 403.
     */
    public function deleteMessage(int $userId, int $messageId): array
    {
        if (!$messageId) {
            return ['success' => false, 'message' => 'Nieprawidłowy identyfikator wiadomości.'];
        }

        if (!$this->messageRepo->isMessageOwnedBy($messageId, $userId)) {
            return ['success' => false, 'message' => 'Nie możesz usunąć tej wiadomości.', 'status' => 403];
        }

        $this->messageRepo->softDeleteMessage($messageId);

        return ['success' => true, 'message' => 'Wiadomość została usunięta.'];
    }

    /**
     * @brief Searches for active users whose name or login matches the query.
     *
     * @details If the query string is less than 2 characters long, returns
     *          an empty array immediately without querying the database.
     *          Otherwise, calls the user repository's searchActive() method
     *          and returns the list of matching users.
     *
     * @param string $query  The search text. Must be at least 2 characters long.
     *
     * @return array A list of matching active user records (up to 15 results).
     *               Each record has keys: user_id, full_name, login, role_name.
     *               Returns an empty array if the query is too short.
     */
    public function searchUsers(string $query): array
    {
        if (mb_strlen($query) < 2) {
            return [];
        }
        return $this->userRepo->searchActive($query);
    }
}
