<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\DashboardRepositoryInterface;

/**
 * @brief Service class that gathers all data needed for the dashboard page.
 *
 * @details This service collects absences, grades, unread message count,
 *          the class schedule, and the session expiry time for a user.
 *          It groups grades by subject name for easier display.
 *          The class contains no SQL, no PDO, and no HTTP code.
 */
class DashboardService
{
    /**
     * @brief Creates a new DashboardService with the required repository.
     *
     * @param DashboardRepositoryInterface $dashboardRepo Repository for all dashboard data.
     */
    public function __construct(
        private readonly DashboardRepositoryInterface $dashboardRepo,
    ) {
    }

    /**
     * @brief Collects and returns all data needed to show the dashboard page.
     *
     * @details Calls several private helper methods to load absences, grades,
     *          unread message count, the lesson schedule, and the session expiry.
     *          Grades are grouped by subject name into a nested array.
     *          If any data source throws an exception, that section returns
     *          an empty array so the rest of the page still loads.
     *
     * @param int    $userId        The ID of the logged-in user.
     * @param string $sessionToken  The current session token (used to get expiry time).
     *
     * @return array{
     *     absences: array,
     *     grades: array,
     *     gradesBySubject: array,
     *     unreadCount: int,
     *     studentClassId: int|null,
     *     todaySchedule: array,
     *     tomorrowSchedule: array,
     *     sessionExpiry: string|null
     * } An array with all dashboard data sections.
     */
    public function getDashboardData(int $userId, string $sessionToken): array
    {
        $absences = $this->getAbsences($userId);
        $grades = $this->getGrades($userId);
        $unreadCount = $this->dashboardRepo->getUnreadMessageCount($userId);
        $schedule = $this->getSchedule($userId);
        $sessionExpiry = $this->dashboardRepo->getSessionExpiry($sessionToken);

        // Group grades by subject name
        $gradesBySubject = [];
        foreach ($grades as $g) {
            $gradesBySubject[$g['subject_name']][] = $g;
        }

        return [
            'absences' => $absences,
            'grades' => $grades,
            'gradesBySubject' => $gradesBySubject,
            'unreadCount' => $unreadCount,
            'studentClassId' => $schedule['studentClassId'],
            'todaySchedule' => $schedule['todaySchedule'],
            'tomorrowSchedule' => $schedule['tomorrowSchedule'],
            'sessionExpiry' => $sessionExpiry,
        ];
    }

    /**
     * @brief Loads the recent absences for a user, returning an empty array on error.
     *
     * @details Calls getRecentAbsences() on the dashboard repository. If the
     *          database query throws any exception (e.g. table does not exist),
     *          the exception is caught and an empty array is returned instead.
     *
     * @param int $userId  The ID of the user (student) to load absences for.
     *
     * @return array A list of recent absence records, or an empty array on error.
     */
    private function getAbsences(int $userId): array
    {
        try {
            return $this->dashboardRepo->getRecentAbsences($userId);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @brief Loads the recent grades for a user, returning an empty array on error.
     *
     * @details Calls getRecentGrades() on the dashboard repository. If the
     *          database query throws any exception (e.g. table does not exist),
     *          the exception is caught and an empty array is returned instead.
     *
     * @param int $userId  The ID of the user (student) to load grades for.
     *
     * @return array A list of recent grade records, or an empty array on error.
     */
    private function getGrades(int $userId): array
    {
        try {
            return $this->dashboardRepo->getRecentGrades($userId);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @brief Loads today's and tomorrow's lesson schedule for a student.
     *
     * @details Determines today's day of the week using PHP's date('w').
     *          If today is a weekend (Saturday or Sunday), today's schedule
     *          is empty and tomorrow's schedule is set to Monday (day_id = 1).
     *          If today is a weekday, both today's and tomorrow's schedules
     *          are fetched. On Friday, tomorrow is treated as Monday (skip weekend).
     *          Day IDs in the database: 1 = Monday, ..., 7 = Sunday.
     *          If the user is not a student or the schedule table does not exist,
     *          all exceptions are caught silently and empty arrays are returned.
     *
     * @param int $userId  The ID of the user to load the schedule for.
     *
     * @return array{
     *     studentClassId: int|null,
     *     todaySchedule: array,
     *     tomorrowSchedule: array
     * } An array with the class ID and both schedule arrays.
     */
    private function getSchedule(int $userId): array
    {
        $todaySchedule = [];
        $tomorrowSchedule = [];
        $studentClassId = null;

        $todayDow = (int) date('w'); // 0=Sun, 6=Sat
        $isWeekend = ($todayDow === 0 || $todayDow === 6);

        // Map PHP date('w') → day_id (1=Mon … 7=Sun)
        $phpToDayId = [0 => 7, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6];

        if (!$isWeekend) {
            $todayDayId = $phpToDayId[$todayDow];
            $tomorrowDow = ($todayDow === 5) ? 1 : ($todayDow + 1);
            $tomorrowDayId = $phpToDayId[$tomorrowDow];
        } else {
            $todayDayId = null;
            $tomorrowDayId = 1; // Monday
        }

        try {
            $studentClassId = $this->dashboardRepo->getStudentClassId($userId);

            if ($studentClassId !== null) {
                if ($todayDayId !== null) {
                    $todaySchedule = $this->dashboardRepo->getScheduleForDay($studentClassId, $todayDayId);
                }
                $tomorrowSchedule = $this->dashboardRepo->getScheduleForDay($studentClassId, $tomorrowDayId);
            }
        } catch (\Exception $e) {
            // User is not a student or the timetable table does not exist
        }

        return [
            'studentClassId' => $studentClassId,
            'todaySchedule' => $todaySchedule,
            'tomorrowSchedule' => $tomorrowSchedule,
        ];
    }
}
