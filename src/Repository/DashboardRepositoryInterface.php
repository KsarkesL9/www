<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Interfejs repozytorium danych dashboardu (nieobecności, oceny, plan zajęć, sesja).
 */
interface DashboardRepositoryInterface
{
    /** Pobiera ostatnie nieobecności ucznia (bez statusu „obecny") */
    public function getRecentAbsences(int $studentId, int $limit = 5): array;

    /** Pobiera ostatnie oceny ucznia */
    public function getRecentGrades(int $studentId, int $limit = 15): array;

    /** Pobiera liczbę nieprzeczytanych wiadomości użytkownika */
    public function getUnreadMessageCount(int $userId): int;

    /** Pobiera class_id ucznia (null jeśli brak) */
    public function getStudentClassId(int $userId): ?int;

    /** Pobiera plan lekcji dla klasy i dnia tygodnia */
    public function getScheduleForDay(int $classId, int $dayOfWeekId): array;

    /** Pobiera czas wygaśnięcia sesji */
    public function getSessionExpiry(string $token): ?string;
}
