<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * @brief Interface for dashboard data repository.
 * 
 * Provides methods to retrieve dashboard information like absences, grades, schedule, and session data.
 */
interface DashboardRepositoryInterface
{
    /**
     * @brief Gets recent absences for a student.
     * 
     * This function retrieves a list of recent absences for a specific student.
     * It excludes the 'present' status.
     * 
     * @param int $studentId The ID of the student.
     * @param int $limit The maximum number of absences to return. Default is 5.
     * @return array An array containing the recent absences.
     */
    public function getRecentAbsences(int $studentId, int $limit = 5): array;

    /**
     * @brief Gets recent grades for a student.
     * 
     * This function retrieves a list of recent student grades from the database.
     * 
     * @param int $studentId The ID of the student.
     * @param int $limit The maximum number of grades to return. Default is 15.
     * @return array An array containing the recent grades.
     */
    public function getRecentGrades(int $studentId, int $limit = 15): array;

    /**
     * @brief Gets the number of unread messages.
     * 
     * This function calculates how many messages the user has not read yet.
     * 
     * @param int $userId The ID of the user.
     * @return int The total number of unread messages.
     */
    public function getUnreadMessageCount(int $userId): int;

    /**
     * @brief Gets the class ID of a student.
     * 
     * This function retrieves the ID of the class that the student belongs to.
     * 
     * @param int $userId The ID of the user (student).
     * @return int|null The ID of the class, or null if not found.
     */
    public function getStudentClassId(int $userId): ?int;

    /**
     * @brief Gets the class schedule for a specific day.
     * 
     * This function returns the timetable for a given class and day of the week.
     * 
     * @param int $classId The ID of the class.
     * @param int $dayOfWeekId The ID of the day of the week (e.g., 1 for Monday).
     * @return array An array containing the subjects in the schedule.
     */
    public function getScheduleForDay(int $classId, int $dayOfWeekId): array;

    /**
     * @brief Gets the session expiration time.
     * 
     * This function checks and returns the expiration date and time for a given session token.
     * 
     * @param string $token The session token string.
     * @return string|null The expiration time as a string, or null if the token is invalid.
     */
    public function getSessionExpiry(string $token): ?string;
}
