<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\AdminCourseException;
use Helpyard\App\Core\Database;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\AdminCourseRepository;
use Helpyard\App\Services\AdminCoursePolicy;
use PDOException;
use RuntimeException;

class AdminCourseController
{
    public function __construct(private array $databaseConfig)
    {
    }

    public function index(array $params = [], ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }

        try {
            $dashboard = (new AdminCourseRepository(Database::connect($this->databaseConfig)))->dashboard();
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $courses = $dashboard['courses'];
        $productsWithoutCourses = $dashboard['products_without_courses'];
        $csrfToken = SessionSecurity::csrfToken();
        $notice = $this->consumeFlash('admin_courses_notice');
        $error = $this->consumeFlash('admin_courses_error');
        $title = 'Course authoring';
        $description = 'Create and publish text lessons for course products.';
        ob_start();
        require __DIR__ . '/../Views/admin/courses.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render course administration.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function createCourse(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $productId = CartController::positiveInteger($request->body()['product_id'] ?? null, PHP_INT_MAX);
        if ($productId === null) {
            return $this->validationError('Choose a valid course product.');
        }

        try {
            $courseId = (new AdminCourseRepository(Database::connect($this->databaseConfig)))
                ->createCourseForProduct($productId, (int) $_SESSION['user_id']);
        } catch (AdminCourseException $exception) {
            return $this->validationError($exception->getMessage());
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if ($courseId === false) {
            return $this->validationError('That product already has a course workspace.');
        }

        $_SESSION['admin_courses_notice'] = 'Course workspace created.';
        return $this->redirect('/admin/courses#course-' . $courseId);
    }

    public function editLesson(array $params, ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }
        $lessonId = CartController::positiveInteger($params['lesson_id'] ?? null, PHP_INT_MAX);
        if ($lessonId === null) {
            return $this->notFound();
        }

        try {
            $lesson = (new AdminCourseRepository(Database::connect($this->databaseConfig)))
                ->findLessonForAdmin($lessonId);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if ($lesson === null) {
            return $this->notFound();
        }

        $csrfToken = SessionSecurity::csrfToken();
        $title = 'Edit course lesson';
        $description = 'Edit and publish a course lesson.';
        ob_start();
        require __DIR__ . '/../Views/admin/course-lesson.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the course lesson editor.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function createSection(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $courseId = CartController::positiveInteger($params['course_id'] ?? null, PHP_INT_MAX);
        $sectionTitle = AdminCoursePolicy::validateSectionTitle($request->body()['title'] ?? null);
        if ($courseId === null || $sectionTitle === null) {
            return $this->validationError('Enter a section title up to 180 characters.');
        }

        try {
            (new AdminCourseRepository(Database::connect($this->databaseConfig)))
                ->createSection($courseId, $sectionTitle, (int) $_SESSION['user_id']);
        } catch (AdminCourseException $exception) {
            return $this->validationError($exception->getMessage());
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $_SESSION['admin_courses_notice'] = 'Course section added.';
        return $this->redirect('/admin/courses#course-' . $courseId);
    }

    public function createLesson(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $sectionId = CartController::positiveInteger($params['section_id'] ?? null, PHP_INT_MAX);
        $lesson = AdminCoursePolicy::validateLesson($request->body());
        if ($sectionId === null || $lesson['error'] !== null) {
            return $this->validationError($lesson['error'] ?? 'Choose a valid course section.');
        }

        try {
            (new AdminCourseRepository(Database::connect($this->databaseConfig)))
                ->createLesson($sectionId, $lesson['data'], (int) $_SESSION['user_id']);
        } catch (AdminCourseException $exception) {
            return $this->validationError($exception->getMessage());
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $_SESSION['admin_courses_notice'] = 'Lesson added.';
        return $this->redirect('/admin/courses');
    }

    public function updateLesson(array $params, Request $request): Response
    {
        if (($response = $this->guardMutation($request)) !== null) {
            return $response;
        }
        $lessonId = CartController::positiveInteger($params['lesson_id'] ?? null, PHP_INT_MAX);
        $lesson = AdminCoursePolicy::validateLesson($request->body());
        if ($lessonId === null || $lesson['error'] !== null) {
            return $this->validationError($lesson['error'] ?? 'Choose a valid lesson.');
        }

        try {
            $updated = (new AdminCourseRepository(Database::connect($this->databaseConfig)))
                ->updateLesson($lessonId, $lesson['data'], (int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if (!$updated) {
            return $this->validationError('That lesson does not exist.');
        }

        $_SESSION['admin_courses_notice'] = 'Lesson updated.';
        return $this->redirect('/admin/courses#course-' . $updated);
    }

    private function guardMutation(Request $request): ?Response
    {
        SessionSecurity::start();
        if (!$this->isAdmin()) {
            return $this->forbiddenOrLogin();
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        return null;
    }

    private function isAdmin(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['user_role'])
            && $_SESSION['user_role'] === 'admin';
    }

    private function forbiddenOrLogin(): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $this->redirect('/login');
        }
        return new Response(403, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Administrator access is required.');
    }

    private function consumeFlash(string $key): string
    {
        $value = $_SESSION[$key] ?? '';
        unset($_SESSION[$key]);

        return is_string($value) ? $value : '';
    }

    private function validationError(string $message): Response
    {
        SessionSecurity::start();
        $_SESSION['admin_courses_error'] = $message;
        return $this->redirect('/admin/courses');
    }

    private function redirect(string $location): Response
    {
        return new Response(303, ['Location' => $location], '');
    }

    private function notFound(): Response
    {
        return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Course lesson not found.');
    }

    private function unavailable(PDOException | RuntimeException $exception): Response
    {
        error_log('Course administration failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Course administration is temporarily unavailable.');
    }
}
