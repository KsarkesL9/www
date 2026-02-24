<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * @brief Interface for thread view repository.
 * 
 * Provides methods for retrieving conversation threads and their participants for the messages page.
 */
interface ThreadViewRepositoryInterface
{
    /**
     * @brief Gets user threads with metadata.
     * 
     * This function retrieves a list of threads for a specific user.
     * The results include additional information like the last message content and the number of unread messages.
     * 
     * @param int $userId The ID of the user.
     * @return array An array containing the list of threads with metadata.
     */
    public function getUserThreads(int $userId): array;

    /**
     * @brief Gets participants for specified threads.
     * 
     * Retrieves the lists of people participating in the given threads, excluding a specific user ID.
     * 
     * @param array $threadIds An array of thread IDs.
     * @param int $excludeUserId The ID of the user to omit from the results.
     * @return array An array mapping thread IDs to their participants.
     */
    public function getParticipantsForThreads(array $threadIds, int $excludeUserId): array;

    /**
     * @brief Gets thread data if the user is a participant.
     * 
     * Fetches details of a specific thread, but only if the specified user is part of it.
     * 
     * @param int $threadId The ID of the thread.
     * @param int $userId The ID of the user.
     * @return array|null An array with thread details if found; otherwise, null.
     */
    public function getThreadIfParticipant(int $threadId, int $userId): ?array;

    /**
     * @brief Gets messages for a thread.
     * 
     * Retrieves all messages belonging to a given conversation thread.
     * 
     * @param int $threadId The ID of the thread.
     * @return array An array containing the thread messages.
     */
    public function getThreadMessages(int $threadId): array;

    /**
     * @brief Gets participants of a thread.
     * 
     * Retrieves a list of all participants in a specific thread, including their user data and roles.
     * 
     * @param int $threadId The ID of the thread.
     * @return array An array containing the thread participants.
     */
    public function getThreadParticipants(int $threadId): array;

    /**
     * @brief Marks a thread as read.
     * 
     * Updates the last read time for the given thread and user, indicating that they have seen all new messages.
     * 
     * @param int $threadId The ID of the thread.
     * @param int $userId The ID of the user.
     */
    public function markThreadAsRead(int $threadId, int $userId): void;
}
