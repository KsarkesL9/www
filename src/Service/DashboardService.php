<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\DashboardRepositoryInterface;

/**
 * Serwis dashboardu — pobiera dane do wyświetlenia na panelu głównym.
 *
 * Zero SQL, zero PDO, zero HTTP.
 */
class DashboardService
{
    public function __construct(
        private readonly DashboardRepositoryInterface $dashboardRepo,
    ) {
    }

    /**
     * Pobiera wszystkie dane potrzebne do wyświetlenia dashboardu.
     *
     * @return array{
     *     absences: array,
     *     grades: array,
     *     gradesBySubject: array,
     *     unreadCount: int,
     *     studentClassId: int|null,
     *     todaySchedule: array,
     *     tomorrowSchedule: array,
     *     sessionExpiry: string|null,
     * }
     */
    public function getDashboardData(int $userId, string $sessionToken): array
    {
        $absences = $this->getAbsences($userId);
        $grades = $this->getGrades($userId);
        $unreadCount = $this->dashboardRepo->getUnreadMessageCount($userId);
        $schedule = $this->getSchedule($userId);
        $sessionExpiry = $this->dashboardRepo->getSessionExpiry($sessionToken);

        // Grupuj oceny po przedmiocie
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

    private function getAbsences(int $userId): array
    {
        try {
            return $this->dashboardRepo->getRecentAbsences($userId);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getGrades(int $userId): array
    {
        try {
            return $this->dashboardRepo->getRecentGrades($userId);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Oblicza plan zajęć z logiką weekendową.
     */
    private function getSchedule(int $userId): array
    {
        $todaySchedule = [];
        $tomorrowSchedule = [];
        $studentClassId = null;

        $todayDow = (int) date('w'); // 0=Sun, 6=Sat
        $isWeekend = ($todayDow === 0 || $todayDow === 6);

        // PHP date('w') → day_id (1=Pon … 7=Ndz)
        $phpToDayId = [0 => 7, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6];

        if (!$isWeekend) {
            $todayDayId = $phpToDayId[$todayDow];
            $tomorrowDow = ($todayDow === 5) ? 1 : ($todayDow + 1);
            $tomorrowDayId = $phpToDayId[$tomorrowDow];
        } else {
            $todayDayId = null;
            $tomorrowDayId = 1; // poniedziałek
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
            // użytkownik nie jest uczniem lub brak planu
        }

        return [
            'studentClassId' => $studentClassId,
            'todaySchedule' => $todaySchedule,
            'tomorrowSchedule' => $tomorrowSchedule,
        ];
    }
}
