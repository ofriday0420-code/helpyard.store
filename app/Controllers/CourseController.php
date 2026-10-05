<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Database;
use Helpyard\App\Core\Request;
use Helpyard\App\Core\Response;
use Helpyard\App\Core\SessionSecurity;
use Helpyard\App\Repositories\CourseRepository;
use PDOException;
use RuntimeException;

class CourseController
{
    public function __construct(private array $databaseConfig)
    {
    }

    public function index(array $params = [], ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }

        try {
            $courses = (new CourseRepository(Database::connect($this->databaseConfig)))
                ->forCustomer((int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        $title = 'My courses';
        $description = 'Access courses included with your paid orders.';
        ob_start();
        require __DIR__ . '/../Views/auth/courses.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the customer courses page.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function show(array $params, ?Request $request = null): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }

        $courseId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        if ($courseId === null) {
            return $this->notFound();
        }

        try {
            $course = (new CourseRepository(Database::connect($this->databaseConfig)))
                ->findForCustomer($courseId, (int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if ($course === null) {
            return $this->notFound();
        }

        $title = $course['name'] . ' — My courses';
        $description = 'Learn from your purchased course.';
        $notice = $this->consumeFlash('course_notice');
        $csrfToken = SessionSecurity::csrfToken();
        ob_start();
        require __DIR__ . '/../Views/auth/course.php';
        $html = ob_get_clean();
        if ($html === false) {
            throw new RuntimeException('Could not render the customer course page.');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }

    public function completeLesson(array $params, Request $request): Response
    {
        SessionSecurity::start();
        if (!$this->authenticated()) {
            return $this->redirect('/login');
        }
        if (!SessionSecurity::verifyCsrfToken($request->body()['csrf_token'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Your form session expired. Please try again.');
        }

        $courseId = CartController::positiveInteger($params['id'] ?? null, PHP_INT_MAX);
        $lessonId = CartController::positiveInteger($params['lesson_id'] ?? null, PHP_INT_MAX);
        if ($courseId === null || $lessonId === null) {
            return $this->notFound();
        }

        try {
            $completed = (new CourseRepository(Database::connect($this->databaseConfig)))
                ->completeLesson($courseId, $lessonId, (int) $_SESSION['user_id']);
        } catch (PDOException | RuntimeException $exception) {
            return $this->unavailable($exception);
        }
        if (!$completed) {
            return $this->notFound();
        }

        $_SESSION['course_notice'] = 'Lesson marked as complete.';

        return $this->redirect('/account/courses/' . $courseId);
    }

    private function authenticated(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['user_role'])
            && $_SESSION['user_role'] === 'customer';
    }

    private function consumeFlash(string $key): string
    {
        $value = $_SESSION[$key] ?? '';
        unset($_SESSION[$key]);

        return is_string($value) ? $value : '';
    }

    private function redirect(string $location): Response
    {
        return new Response(303, ['Location' => $location], '');
    }

    private function notFound(): Response
    {
        return new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Course not found.');
    }

    private function unavailable(PDOException | RuntimeException $exception): Response
    {
        error_log('Customer course request failed: ' . $exception->getMessage());

        return new Response(503, ['Content-Type' => 'text/html; charset=UTF-8'], 'Courses are temporarily unavailable.');
    }
}
