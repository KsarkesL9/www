<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Implementacja repozytorium dashboardu oparta na PDO.
 */
class PdoDashboardRepository implements DashboardRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getRecentAbsences(int $studentId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                a.lesson_date,
                ast.name   AS status_name,
                a.excuse_note
             FROM attendance a
             JOIN attendance_statuses ast ON ast.status_id = a.status_id
             WHERE a.student_id = ?
               AND ast.name != 'obecny'
             ORDER BY a.lesson_date DESC
             LIMIT ?"
        );
        $stmt->execute([$studentId, $limit]);
        return $stmt->fetchAll();
    }

    public function getRecentGrades(int $studentId, int $limit = 15): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                g.grade_id,
                g.grade,
                g.description,
                g.graded_at,
                g.created_at,
                g.color,
                s.name          AS subject_name,
                gc.name         AS category_name,
                gc.weight,
                (g.created_at >= NOW() - INTERVAL 7 DAY) AS is_new
             FROM grades g
             JOIN subjects s      ON s.subject_id  = g.subject_id
             LEFT JOIN grade_categories gc ON gc.category_id = g.category_id
             WHERE g.student_id = ?
             ORDER BY g.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$studentId, $limit]);
        return $stmt->fetchAll();
    }

    public function getUnreadMessageCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM messages m
             JOIN message_thread_participants mtp
                 ON mtp.thread_id = m.thread_id
                AND mtp.user_id   = ?
             WHERE m.sender_id != ?
               AND m.deleted_at  IS NULL
               AND (
                   mtp.last_read_at IS NULL
                   OR m.created_at > mtp.last_read_at
               )'
        );
        $stmt->execute([$userId, $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getStudentClassId(int $userId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT class_id FROM students WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['class_id'] : null;
    }

    public function getScheduleForDay(int $classId, int $dayOfWeekId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                vct.lesson_number,
                vct.start_hour,
                vct.end_hour,
                vct.subject_name,
                vct.teacher_name,
                vct.classroom_name
             FROM v_current_timetable vct
             WHERE vct.class_id      = ?
               AND vct.day_of_week_id = ?
             ORDER BY vct.lesson_number ASC'
        );
        $stmt->execute([$classId, $dayOfWeekId]);
        return $stmt->fetchAll();
    }

    public function getSessionExpiry(string $token): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT expires_at FROM user_sessions
             WHERE token = ? AND revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ? $row['expires_at'] : null;
    }
}
