<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Message;

/**
 * Interfejs repozytorium wiadomości / wątków.
 */
interface MessageRepositoryInterface
{
    /** Rozpoczyna transakcję */
    public function beginTransaction(): void;

    /** Zatwierdza transakcję */
    public function commit(): void;

    /** Wycofuje transakcję */
    public function rollBack(): void;

    /** Tworzy wątek — zwraca thread_id */
    public function createThread(?string $subject): int;

    /** Dodaje uczestnika do wątku */
    public function addParticipant(int $threadId, int $userId): void;

    /** Sprawdza czy użytkownik jest uczestnikiem wątku */
    public function isParticipant(int $threadId, int $userId): bool;

    /** Wstawia wiadomość — zwraca message_id */
    public function insertMessage(int $threadId, int $senderId, string $content): int;

    /** Aktualizuje last_read_at dla uczestnika */
    public function updateLastRead(int $threadId, int $userId): void;

    /** Pobiera wiadomość po ID */
    public function findMessageById(int $messageId): ?Message;

    /** Sprawdza czy wiadomość należy do użytkownika i nie jest usunięta */
    public function isMessageOwnedBy(int $messageId, int $userId): bool;

    /** Soft-delete wiadomości */
    public function softDeleteMessage(int $messageId): void;
}
