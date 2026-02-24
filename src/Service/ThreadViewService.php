<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ThreadViewRepositoryInterface;

/**
 * @brief Service class for loading message thread data for the messages page.
 *
 * @details This service provides two methods: one to get the list of
 *          threads in the sidebar, and one to load all data for a single
 *          open thread. Exceptions from the repository are caught so the
 *          page still loads even if some data is missing.
 *          The class contains no SQL, no PDO, and no HTTP code.
 */
class ThreadViewService
{
    /**
     * @brief Creates a new ThreadViewService with the required repository.
     *
     * @param ThreadViewRepositoryInterface $threadViewRepo Repository for thread view queries.
     */
    public function __construct(
        private readonly ThreadViewRepositoryInterface $threadViewRepo,
    ) {
    }

    /**
     * @brief Loads the thread list and participant data for the sidebar.
     *
     * @details Calls getUserThreads() to get all threads for the user.
     *          If this fails, returns empty arrays. If threads are found,
     *          extracts all thread IDs and calls getParticipantsForThreads()
     *          to load the other participants for each thread (excluding the
     *          current user). The result is a map of thread_id => participant list.
     *          If loading participants fails, an empty array is used.
     *
     * @param int $userId  The ID of the logged-in user.
     *
     * @return array{threads: array, threadParticipants: array}
     *         'threads' is a list of thread rows with last message preview data.
     *         'threadParticipants' is a map: thread_id => array of participant records.
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
                // No participants found
            }
        }

        return [
            'threads' => $threads,
            'threadParticipants' => $threadParticipants,
        ];
    }

    /**
     * @brief Loads all data for a single open thread.
     *
     * @details Returns empty data if the thread ID is zero or negative.
     *          Calls getThreadIfParticipant() to verify that the user is a
     *          participant in the given thread. If not, returns empty data.
     *          If the user is a participant, marks the thread as read, then
     *          loads all messages and all participant records for the thread.
     *          Each section is wrapped in try/catch so partial failures do
     *          not crash the whole page.
     *
     * @param int $threadId  The ID of the thread to open. Use 0 if no thread is open.
     * @param int $userId    The ID of the logged-in user.
     *
     * @return array{thread: array|null, messages: array, participants: array}
     *         'thread' is the thread row, or null if not found or access denied.
     *         'messages' is a list of message rows ordered by creation time.
     *         'participants' is a list of all participant records for the thread.
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

        // Mark as read
        try {
            $this->threadViewRepo->markThreadAsRead($threadId, $userId);
        } catch (\Exception $e) {
            // Not critical if this fails
        }

        try {
            $messages = $this->threadViewRepo->getThreadMessages($threadId);
        } catch (\Exception $e) {
            // No messages
        }

        try {
            $participants = $this->threadViewRepo->getThreadParticipants($threadId);
        } catch (\Exception $e) {
            // No participants
        }

        return compact('thread', 'messages', 'participants');
    }
}
