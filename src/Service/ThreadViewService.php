<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ThreadViewRepositoryInterface;

/**
 * Serwis widoku wiadomości — pobiera dane do wyświetlenia na stronie messages.php.
 *
 * Zero SQL, zero PDO, zero HTTP.
 */
class ThreadViewService
{
    public function __construct(
        private readonly ThreadViewRepositoryInterface $threadViewRepo,
    ) {
    }

    /**
     * Pobiera listę wątków z uczestnikami.
     *
     * @return array{threads: array, threadParticipants: array}
     */
    public function getThreadList(int $userId): array
    {
        try {
            $threads = $this->threadViewRepo->getUserThreads($userId);
        } catch (\Exception $e) {
            $threads = [];
        }

        $threadParticipants = [];
        if (!empty($threads)) {
            $threadIds = array_column($threads, 'thread_id');
            try {
                $threadParticipants = $this->threadViewRepo->getParticipantsForThreads($threadIds, $userId);
            } catch (\Exception $e) {
                // brak uczestników
            }
        }

        return [
            'threads' => $threads,
            'threadParticipants' => $threadParticipants,
        ];
    }

    /**
     * Pobiera dane aktywnego wątku (jeśli użytkownik jest uczestnikiem).
     *
     * @return array{thread: array|null, messages: array, participants: array}
     */
    public function getActiveThread(int $threadId, int $userId): array
    {
        $thread = null;
        $messages = [];
        $participants = [];

        if ($threadId <= 0) {
            return compact('thread', 'messages', 'participants');
        }

        try {
            $thread = $this->threadViewRepo->getThreadIfParticipant($threadId, $userId);
        } catch (\Exception $e) {
            return compact('thread', 'messages', 'participants');
        }

        if (!$thread) {
            return compact('thread', 'messages', 'participants');
        }

        // Oznacz jako przeczytane
        try {
            $this->threadViewRepo->markThreadAsRead($threadId, $userId);
        } catch (\Exception $e) {
            // nie krytyczne
        }

        try {
            $messages = $this->threadViewRepo->getThreadMessages($threadId);
        } catch (\Exception $e) {
            // brak wiadomości
        }

        try {
            $participants = $this->threadViewRepo->getThreadParticipants($threadId);
        } catch (\Exception $e) {
            // brak uczestników
        }

        return compact('thread', 'messages', 'participants');
    }
}
