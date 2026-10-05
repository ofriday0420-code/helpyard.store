<?php

namespace Helpyard\App\Repositories;

use PDO;

class CourseRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function forCustomer(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT c.id, p.name, p.slug, COUNT(DISTINCT l.id) AS lesson_count, '
            . 'COUNT(DISTINCT CASE WHEN progress.completed_at IS NOT NULL THEN l.id END) AS completed_count '
            . 'FROM course_enrollments e '
            . 'JOIN orders o ON o.id = e.order_id AND o.user_id = e.user_id AND o.status = \'paid\' '
            . 'JOIN courses c ON c.id = e.course_id '
            . 'JOIN products p ON p.id = c.product_id AND p.product_type = \'course\' '
            . 'LEFT JOIN course_sections s ON s.course_id = c.id '
            . 'LEFT JOIN course_lessons l ON l.section_id = s.id AND l.is_published = 1 '
            . 'LEFT JOIN course_lesson_progress progress ON progress.lesson_id = l.id AND progress.user_id = e.user_id '
            . 'WHERE e.user_id = :user_id AND e.revoked_at IS NULL '
            . 'GROUP BY c.id, p.name, p.slug ORDER BY p.name, c.id'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function findForCustomer(int $courseId, int $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT c.id AS course_id, p.name AS course_name, p.slug, '
            . 's.id AS section_id, s.title AS section_title, '
            . 'l.id AS lesson_id, l.title AS lesson_title, l.content AS lesson_content, '
            . 'progress.completed_at '
            . 'FROM courses c '
            . 'JOIN products p ON p.id = c.product_id AND p.product_type = \'course\' '
            . 'LEFT JOIN course_sections s ON s.course_id = c.id '
            . 'LEFT JOIN course_lessons l ON l.section_id = s.id AND l.is_published = 1 '
            . 'LEFT JOIN course_lesson_progress progress ON progress.lesson_id = l.id AND progress.user_id = :progress_user_id '
            . 'WHERE c.id = :course_id AND EXISTS ('
            . 'SELECT 1 FROM course_enrollments e JOIN orders o ON o.id = e.order_id '
            . 'AND o.user_id = e.user_id AND o.status = \'paid\' '
            . 'WHERE e.course_id = c.id AND e.user_id = :enrollment_user_id AND e.revoked_at IS NULL'
            . ') ORDER BY s.position, s.id, l.position, l.id'
        );
        $statement->execute([
            'progress_user_id' => $userId,
            'course_id' => $courseId,
            'enrollment_user_id' => $userId,
        ]);

        $rows = $statement->fetchAll();
        if ($rows === []) {
            return null;
        }

        $course = [
            'id' => (int) $rows[0]['course_id'],
            'name' => $rows[0]['course_name'],
            'slug' => $rows[0]['slug'],
            'sections' => [],
        ];
        $sectionIndexes = [];
        foreach ($rows as $row) {
            if ($row['section_id'] === null) {
                continue;
            }

            $sectionId = (int) $row['section_id'];
            if (!isset($sectionIndexes[$sectionId])) {
                $sectionIndexes[$sectionId] = count($course['sections']);
                $course['sections'][] = [
                    'id' => $sectionId,
                    'title' => $row['section_title'],
                    'lessons' => [],
                ];
            }
            if ($row['lesson_id'] === null) {
                continue;
            }

            $course['sections'][$sectionIndexes[$sectionId]]['lessons'][] = [
                'id' => (int) $row['lesson_id'],
                'title' => $row['lesson_title'],
                'content' => $row['lesson_content'],
                'completed_at' => $row['completed_at'],
            ];
        }

        return $course;
    }

    public function completeLesson(int $courseId, int $lessonId, int $userId): bool
    {
        $this->connection->beginTransaction();
        try {
            $access = $this->connection->prepare(
                'SELECT l.id FROM course_lessons l '
                . 'JOIN course_sections s ON s.id = l.section_id '
                . 'JOIN courses c ON c.id = s.course_id '
                . 'JOIN course_enrollments e ON e.course_id = c.id AND e.user_id = :enrollment_user_id '
                . 'AND e.revoked_at IS NULL '
                . 'JOIN orders o ON o.id = e.order_id AND o.user_id = e.user_id AND o.status = \'paid\' '
                . 'WHERE l.id = :lesson_id AND c.id = :course_id AND l.is_published = 1 '
                . 'LIMIT 1 FOR UPDATE'
            );
            $access->execute([
                'enrollment_user_id' => $userId,
                'lesson_id' => $lessonId,
                'course_id' => $courseId,
            ]);
            if ($access->fetchColumn() === false) {
                $this->connection->rollBack();
                return false;
            }

            $progress = $this->connection->prepare(
                'INSERT INTO course_lesson_progress (user_id, lesson_id, completed_at) '
                . 'VALUES (:user_id, :lesson_id, UTC_TIMESTAMP()) '
                . 'ON DUPLICATE KEY UPDATE completed_at = course_lesson_progress.completed_at'
            );
            $progress->execute(['user_id' => $userId, 'lesson_id' => $lessonId]);
            $this->connection->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
