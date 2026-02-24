<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\MessageRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * Serwis wiadomości — tworzenie wątków, wysyłanie, usuwanie.
 *
 * Zero SQL, zero PDO, zero HTTP.
 * Transakcje zarządzane wewnętrznie przez repozytorium.
 */
class MessageService
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepo,
        private readonly UserRepositoryInterface $userRepo,
    ) {
    }

    /**
     * Tworzy nowy wątek z pierwszą wiadomością.
     *
     * @return array{success: bool, message: string, thread_id?: int, message_id?: int}
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

        // Weryfikacja aktywnych odbiorców
        $validRecipients = $this->userRepo->filterActiveUserIds($recipientIds);
        if (empty($validRecipients)) {
            return ['success' => false, 'message' => 'Żaden z podanych odbiorców nie ma aktywnego konta.'];
        }

        $this->messageRepo->beginTransaction();
        try {
            $threadId = $this->messageRepo->createThread($subject ?: null);

            // Dodaj uczestników
            $allParticipants = array_unique(array_merge([$senderId], $validRecipients));
            foreach ($allParticipants as $participantId) {
                $this->messageRepo->addParticipant($threadId, (int) $participantId);
            }

            // Wyślij pierwszą wiadomość
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
     * Wysyła wiadomość w istniejącym wątku.
     *
     * @return array{success: bool, message: string, data?: array}
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
     * Soft-delete wiadomości.
     *
     * @return array{success: bool, message: string, status?: int}
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
     * Wyszukuje aktywnych użytkowników.
     */
    public function searchUsers(string $query): array
    {
        if (mb_strlen($query) < 2) {
            return [];
        }
        return $this->userRepo->searchActive($query);
    }
}
