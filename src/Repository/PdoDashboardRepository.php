<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * @file PdoDashboardRepository.php
 * @brief PDO-based implementation of DashboardRepositoryInterface.
 *
 * @details Provides all read queries needed to build the dashboard page.
 *          Covers absences, grades, unread message count, student class ID,
 *          lesson schedule, and session expiry time. All methods are
 *          read-only queries. No business logic is included here.
 */
class PdoDashboardRepository implements DashboardRepositoryInterface
{
    /**
     * @brief Creates a new PdoDashboardRepository with a PDO connection.
     *
     * @param PDO $pdo An active PDO database connection.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @brief Returns recent absence records for a student.
     *
     * @details Queries the attendance table joined with attendance_statuses.
     *          Only rows where the status name is not 'obecny' (present) are
     *          returned. Results are ordered by lesson_date DESC and limited
     *          to $limit rows. Each row contains lesson_date, status_name,
     *          and excuse_note.
     *
     * @param int $studentId The user ID of the student.
     * @param int $limit     Maximum number of rows to return (default 5).
     *
     * @return array A list of absence rows ordered by date, newest first.
     */
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

    /**
     * @brief Returns recent grade records for a student.
     *
     * @details Queries the grades table joined with subjects and grade_categories.
     *          The is_new column is 1 if the grade was added in the last 7 days.
     *          Results are ordered by created_at DESC and limited to $limit rows.
     *          Each row contains grade_id, grade, description, graded_at, created_at,
     *          color, subject_name, category_name, weight, and is_new.
     *
     * @param int $studentId The user ID of the student.
     * @param int $limit     Maximum number of rows to return (default 15).
     *
     * @return array A list of grade rows ordered by creation time, newest first.
     */
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

    /**
     * @brief Counts the number of unread messages for a user.
     *
     * @details Counts messages in threads where the user is a participant,
     *          the message was not sent by the user, the message is not deleted,
     *          and the message was created after the user's last_read_at time
     *          (or last_read_at IS NULL meaning never read).
     *
     * @param int $userId The ID of the user to count unread messages for.
     *
     * @return int The total number of unread messages.
     */
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

    /**
     * @brief Returns the class ID that a student belongs to.
     *
     * @details Queries the students table for the class_id of the given user.
     *          Returns null if the user is not a student or has no class assigned.
     *
     * @param int $userId The ID of the user (student) to look up.
     *
     * @return int|null The class ID, or null if not found.
     */
    public function getStudentClassId(int $userId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT class_id FROM students WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['class_id'] : null;
    }

    /**
     * @brief Returns the lesson schedule for a class on a given day of the week.
     *
     * @details Queries the v_current_timetable view filtered by class_id and
     *          day_of_week_id. Results are ordered by lesson_number ASC.
     *          Day IDs in the database: 1 = Monday, ..., 7 = Sunday.
     *          Each row contains lesson_number, start_hour, end_hour,
     *          subject_name, teacher_name, and classroom_name.
     *
     * @param int $classId     The ID of the student's class.
     * @param int $dayOfWeekId The day of week ID (1 = Monday, 5 = Friday, etc.).
     *
     * @return array A list of lesson rows for that day, ordered by lesson number.
     */
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

    /**
     * @brief Returns the expiry timestamp of a session by its token.
     *
     * @details Queries user_sessions for a row matching the token where
     *          revoked_at IS NULL. Returns the expires_at string if found,
     *          or null if no active session exists for that token.
     *
     * @param string $token The session token to look up.
     *
     * @return string|null The expires_at datetime string, or null if not found.
     */
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
