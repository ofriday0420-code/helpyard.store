<?php

namespace Helpyard\App\Repositories;

use Helpyard\App\Core\AdminCourseException;
use PDO;

class AdminCourseRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function dashboard(): array
    {
        $products = $this->connection->query(
            'SELECT p.id AS product_id, p.name AS product_name, p.slug, p.is_active, c.id AS course_id '
            . 'FROM products p LEFT JOIN courses c ON c.product_id = p.id '
            . "WHERE p.product_type = 'course' ORDER BY p.name, p.id"
        )->fetchAll();

        $courses = [];
        $courseIndexes = [];
        $productIdsWithoutCourses = [];
        foreach ($products as $product) {
            if ($product['course_id'] === null) {
                $productIdsWithoutCourses[] = $product;
                continue;
            }

            $courseId = (int) $product['course_id'];
            $courseIndexes[$courseId] = count($courses);
            $courses[] = [
                'id' => $courseId,
                'product_id' => (int) $product['product_id'],
                'name' => $product['product_name'],
                'slug' => $product['slug'],
                'is_active' => (int) $product['is_active'],
                'sections' => [],
            ];
        }

        if ($courseIndexes !== []) {
            $placeholders = implode(',', array_fill(0, count($courseIndexes), '?'));
            $sections = $this->connection->prepare(
                'SELECT s.id AS section_id, s.course_id, s.title AS section_title, s.position AS section_position, '
                . 'l.id AS lesson_id, l.title AS lesson_title, '
                . 'l.position AS lesson_position, l.is_published '
                . 'FROM course_sections s LEFT JOIN course_lessons l ON l.section_id = s.id '
                . 'WHERE s.course_id IN (' . $placeholders . ') '
                . 'ORDER BY s.course_id, s.position, s.id, l.position, l.id'
            );
            $sections->execute(array_keys($courseIndexes));
            $sectionIndexes = [];
            foreach ($sections->fetchAll() as $row) {
                $courseIndex = $courseIndexes[(int) $row['course_id']];
                $sectionId = (int) $row['section_id'];
                if (!isset($sectionIndexes[$sectionId])) {
                    $sectionIndexes[$sectionId] = count($courses[$courseIndex]['sections']);
                    $courses[$courseIndex]['sections'][] = [
                        'id' => $sectionId,
                        'title' => $row['section_title'],
                        'position' => (int) $row['section_position'],
                        'lessons' => [],
                    ];
                }
                if ($row['lesson_id'] !== null) {
                    $sectionIndex = $sectionIndexes[$sectionId];
                    $courses[$courseIndex]['sections'][$sectionIndex]['lessons'][] = [
                        'id' => (int) $row['lesson_id'],
                        'title' => $row['lesson_title'],
                        'position' => (int) $row['lesson_position'],
                        'is_published' => (int) $row['is_published'],
                    ];
                }
            }
        }

        return ['courses' => $courses, 'products_without_courses' => $productIdsWithoutCourses];
    }

    public function findLessonForAdmin(int $lessonId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT l.id, l.section_id, l.title, l.content, l.position, l.is_published, '
            . 's.title AS section_title, c.id AS course_id, p.name AS course_name '
            . 'FROM course_lessons l '
            . 'JOIN course_sections s ON s.id = l.section_id '
            . 'JOIN courses c ON c.id = s.course_id '
            . 'JOIN products p ON p.id = c.product_id '
            . 'WHERE l.id = :lesson_id LIMIT 1'
        );
        $statement->execute(['lesson_id' => $lessonId]);
        $lesson = $statement->fetch();

        return $lesson === false ? null : $lesson;
    }

    public function createCourseForProduct(int $productId, int $adminId): int|false
    {
        $this->connection->beginTransaction();
        try {
            $product = $this->connection->prepare(
                "SELECT id, name FROM products WHERE id = :product_id AND product_type = 'course' FOR UPDATE"
            );
            $product->execute(['product_id' => $productId]);
            $productRow = $product->fetch();
            if ($productRow === false) {
                throw new AdminCourseException('Choose an existing course product.');
            }

            $existing = $this->connection->prepare('SELECT id FROM courses WHERE product_id = :product_id');
            $existing->execute(['product_id' => $productId]);
            if ($existing->fetchColumn() !== false) {
                $this->connection->rollBack();
                return false;
            }

            $insert = $this->connection->prepare('INSERT INTO courses (product_id) VALUES (:product_id)');
            $insert->execute(['product_id' => $productId]);
            $courseId = (int) $this->connection->lastInsertId();
            $this->audit($adminId, 'course.created', 'course', $courseId, [
                'product_id' => $productId,
                'product_name' => $productRow['name'],
            ]);
            $this->connection->commit();

            return $courseId;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function createSection(int $courseId, string $title, int $adminId): int
    {
        $this->connection->beginTransaction();
        try {
            $course = $this->connection->prepare(
                'SELECT id FROM courses WHERE id = :course_id FOR UPDATE'
            );
            $course->execute(['course_id' => $courseId]);
            if ($course->fetchColumn() === false) {
                throw new AdminCourseException('That course does not exist.');
            }

            $position = $this->nextPosition('course_sections', 'course_id', $courseId);
            $insert = $this->connection->prepare(
                'INSERT INTO course_sections (course_id, title, position) '
                . 'VALUES (:course_id, :title, :position)'
            );
            $insert->execute(['course_id' => $courseId, 'title' => $title, 'position' => $position]);
            $sectionId = (int) $this->connection->lastInsertId();
            $this->audit($adminId, 'course_section.created', 'course_section', $sectionId, [
                'course_id' => $courseId,
                'title' => $title,
                'position' => $position,
            ]);
            $this->connection->commit();

            return $sectionId;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function createLesson(int $sectionId, array $lesson, int $adminId): int
    {
        $this->connection->beginTransaction();
        try {
            $section = $this->connection->prepare(
                'SELECT id, course_id FROM course_sections WHERE id = :section_id FOR UPDATE'
            );
            $section->execute(['section_id' => $sectionId]);
            $sectionRow = $section->fetch();
            if ($sectionRow === false) {
                throw new AdminCourseException('That course section does not exist.');
            }

            $position = $this->nextPosition('course_lessons', 'section_id', $sectionId);
            $insert = $this->connection->prepare(
                'INSERT INTO course_lessons (section_id, title, content, position, is_published) '
                . 'VALUES (:section_id, :title, :content, :position, :is_published)'
            );
            $insert->execute([
                'section_id' => $sectionId,
                'title' => $lesson['title'],
                'content' => $lesson['content'],
                'position' => $position,
                'is_published' => $lesson['is_published'],
            ]);
            $lessonId = (int) $this->connection->lastInsertId();
            $this->audit($adminId, 'course_lesson.created', 'course_lesson', $lessonId, [
                'course_id' => (int) $sectionRow['course_id'],
                'section_id' => $sectionId,
                'title' => $lesson['title'],
                'is_published' => $lesson['is_published'],
                'content_sha256' => hash('sha256', $lesson['content']),
            ]);
            $this->connection->commit();

            return $lessonId;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function updateLesson(int $lessonId, array $lesson, int $adminId): int|false
    {
        $this->connection->beginTransaction();
        try {
            $lookup = $this->connection->prepare(
                'SELECT l.section_id, l.title, l.content, l.is_published, s.course_id '
                . 'FROM course_lessons l JOIN course_sections s ON s.id = l.section_id '
                . 'WHERE l.id = :lesson_id FOR UPDATE'
            );
            $lookup->execute(['lesson_id' => $lessonId]);
            $before = $lookup->fetch();
            if ($before === false) {
                $this->connection->rollBack();
                return false;
            }

            $update = $this->connection->prepare(
                'UPDATE course_lessons SET title = :title, content = :content, is_published = :is_published '
                . 'WHERE id = :lesson_id'
            );
            $update->execute([
                'title' => $lesson['title'],
                'content' => $lesson['content'],
                'is_published' => $lesson['is_published'],
                'lesson_id' => $lessonId,
            ]);
            $this->audit($adminId, 'course_lesson.updated', 'course_lesson', $lessonId, [
                'course_id' => (int) $before['course_id'],
                'section_id' => (int) $before['section_id'],
                'before' => [
                    'title' => $before['title'],
                    'is_published' => (int) $before['is_published'],
                    'content_sha256' => hash('sha256', $before['content']),
                ],
                'after' => [
                    'title' => $lesson['title'],
                    'is_published' => $lesson['is_published'],
                    'content_sha256' => hash('sha256', $lesson['content']),
                ],
            ]);
            $this->connection->commit();

            return (int) $before['course_id'];
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function nextPosition(string $table, string $foreignKey, int $parentId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM ' . $table . ' WHERE ' . $foreignKey . ' = :parent_id'
        );
        $statement->execute(['parent_id' => $parentId]);

        return (int) $statement->fetchColumn();
    }

    private function audit(int $adminId, string $action, string $subjectType, int $subjectId, array $details): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO admin_audit_logs (actor_user_id, action, subject_type, subject_id, details) '
            . 'VALUES (:actor_user_id, :action, :subject_type, :subject_id, :details)'
        );
        $statement->execute([
            'actor_user_id' => $adminId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
        ]);
    }

    private function rollback(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }
}
