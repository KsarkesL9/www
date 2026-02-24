<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Interfejs repozytorium widoku wątków (zapytania dla strony messages.php).
 */
interface ThreadViewRepositoryInterface
{
    /** Pobiera listę wątków użytkownika z metadanymi (ostatnia wiadomość, unread count, itp.) */
    public function getUserThreads(int $userId): array;

    /** Pobiera uczestników dla podanych wątków (oprócz danego userId) */
    public function getParticipantsForThreads(array $threadIds, int $excludeUserId): array;

    /** Pobiera dane aktywnego wątku (jeśli użytkownik jest uczestnikiem) */
    public function getThreadIfParticipant(int $threadId, int $userId): ?array;

    /** Pobiera wiadomości wątku */
    public function getThreadMessages(int $threadId): array;

    /** Pobiera uczestników wątku (z danymi użytkownika i roli) */
    public function getThreadParticipants(int $threadId): array;

    /** Oznacza wątek jako przeczytany dla danego użytkownika */
    public function markThreadAsRead(int $threadId, int $userId): void;
}
