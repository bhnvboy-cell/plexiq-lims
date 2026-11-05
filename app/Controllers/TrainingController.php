<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class TrainingController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $coursesResult = \App\Helpers\Pagination::run($db, "
            SELECT c.*,
                (SELECT COUNT(*) FROM training_assignments ta WHERE ta.course_id = c.id) AS assigned_count,
                (SELECT COUNT(*) FROM training_assignments ta WHERE ta.course_id = c.id AND ta.completed_date IS NOT NULL) AS completed_count
            FROM training_courses c
        ", "
            SELECT COUNT(*) FROM training_courses c
        ", [], 20, 'c.created_at DESC');
        $assignments = $db->query("
            SELECT ta.*, c.course_name, c.course_code, u.full_name AS user_name
            FROM training_assignments ta
            JOIN training_courses c ON ta.course_id = c.id
            JOIN users u ON ta.user_id = u.id
            ORDER BY ta.due_date ASC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('training.index', [
            'courses' => $coursesResult['items'],
            'paginator' => $coursesResult,
            'assignments' => $assignments,
        ]);
    }

    public function courses(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $result = \App\Helpers\Pagination::run($db, "
            SELECT c.*, u.full_name AS created_by_name
            FROM training_courses c
            LEFT JOIN users u ON c.created_by = u.id
        ", "
            SELECT COUNT(*)
            FROM training_courses c
        ", [], 20, 'c.course_name');
        return $this->render('training.courses', ['courses' => $result['items'], 'paginator' => $result]);
    }

    public function createCourse(): string
    {
        Auth::requireRole('Admin');
        return $this->render('training.course-form', ['course' => null]);
    }

    public function storeCourse(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO training_courses (course_code, course_name, description, course_type, duration_hours, validity_days, provider, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['course_code'],
            $_POST['course_name'],
            $_POST['description'] ?? null,
            $_POST['category'] ?? $_POST['course_type'] ?? 'General',
            $_POST['duration_hours'] ?: null,
            $_POST['frequency_days'] ?? $_POST['validity_days'] ?: null,
            $_POST['provider'] ?? null,
            Auth::id(),
        ]);
        $courseId = $db->lastInsertId();
        Audit::log('Training Course Created', 'training_courses', $courseId);
        session_flash('success', 'Training course created.');
        $this->redirect('/training/courses');
    }

    public function editCourse(int $id): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM training_courses WHERE id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$course) { session_flash('error', 'Course not found.'); $this->redirect('/training/courses'); }
        return $this->render('training.course-form', ['course' => $course]);
    }

    public function updateCourse(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $isActive = !empty($_POST['is_active']) ? 't' : 'f';
        $isMandatory = (!empty($_POST['is_mandatory']) || !empty($_POST['requires_certification'])) ? 't' : 'f';
        try {
            $db->prepare("UPDATE training_courses SET course_code = ?, course_name = ?, description = ?, course_type = ?, duration_hours = ?, validity_days = ?, provider = ?, is_mandatory = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
                $_POST['course_code'],
                $_POST['course_name'],
                $_POST['description'] ?? null,
                $_POST['category'] ?? $_POST['course_type'] ?? 'General',
                ($_POST['duration_hours'] ?? '') !== '' ? $_POST['duration_hours'] : null,
                ($_POST['frequency_days'] ?? $_POST['validity_days'] ?? '') !== '' ? ($_POST['frequency_days'] ?? $_POST['validity_days']) : null,
                $_POST['provider'] ?? null,
                $isMandatory,
                $isActive,
                $id,
            ]);
        } catch (\Throwable) {
            $db->prepare("UPDATE training_courses SET course_code = ?, course_name = ?, description = ?, course_type = ?, duration_hours = ?, validity_days = ?, provider = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
                $_POST['course_code'],
                $_POST['course_name'],
                $_POST['description'] ?? null,
                $_POST['category'] ?? $_POST['course_type'] ?? 'General',
                ($_POST['duration_hours'] ?? '') !== '' ? $_POST['duration_hours'] : null,
                ($_POST['frequency_days'] ?? $_POST['validity_days'] ?? '') !== '' ? ($_POST['frequency_days'] ?? $_POST['validity_days']) : null,
                $_POST['provider'] ?? null,
                $isActive,
                $id,
            ]);
        }
        Audit::log('Training Course Updated', 'training_courses', $id);
        session_flash('success', 'Training course updated.');
        $this->redirect('/training/courses');
    }

    public function assignments(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $userAssignments = $db->prepare("
            SELECT ta.*, c.course_name, c.course_code, c.duration_hours
            FROM training_assignments ta
            JOIN training_courses c ON ta.course_id = c.id
            WHERE ta.user_id = ?
            ORDER BY ta.due_date ASC
        ");
        $userAssignments->execute([Auth::id()]);
        return $this->render('training.assignments', ['assignments' => $userAssignments->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function storeAssignment(): void
    {
        $this->assignUser();
    }

    public function assignUser(?int $courseId = null): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $courseId = $courseId ?: (int)($_POST['course_id'] ?? 0);
        $dueDate = ($_POST['due_date'] ?? '') !== '' ? $_POST['due_date'] : date('Y-m-d', strtotime('+30 days'));
        $db->prepare("INSERT INTO training_assignments (course_id, user_id, assigned_by, due_date) VALUES (?, ?, ?, ?)")->execute([
            $courseId,
            $_POST['user_id'],
            Auth::id(),
            $dueDate,
        ]);
        Audit::log('Training Assigned', 'training_assignments', null, null, ['course_id' => $courseId, 'user_id' => $_POST['user_id']]);
        session_flash('success', 'Training assigned.');
        $this->redirect('/training');
    }

    public function recordCompletion(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $completedDate = ($_POST['completed_date'] ?? '') !== '' ? $_POST['completed_date'] : date('Y-m-d');
        $passed = !empty($_POST['passed']) ? 't' : 'f';
        $score = ($_POST['score'] ?? '') !== '' ? $_POST['score'] : null;
        $notes = $_POST['notes'] ?? null;
        try {
            $db->prepare("UPDATE training_assignments SET completed_date = ?, passed = ?, score = ?, notes = ?, completed_by = ?, status = 'Completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
                $completedDate,
                $passed,
                $score,
                $notes,
                Auth::id(),
                $id,
            ]);
        } catch (\Throwable) {
            try {
                $db->prepare("UPDATE training_assignments SET completed_date = ?, passed = ?, score = ?, notes = ?, completed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
                    $completedDate,
                    $passed,
                    $score,
                    $notes,
                    Auth::id(),
                    $id,
                ]);
            } catch (\Throwable) {
                $db->prepare("UPDATE training_assignments SET completed_date = ?, score = ?, notes = ?, status = 'Completed' WHERE id = ?")->execute([
                    $completedDate,
                    $score,
                    $notes,
                    $id,
                ]);
            }
        }
        Audit::log('Training Completed', 'training_assignments', $id);
        session_flash('success', 'Completion recorded.');
        $this->redirect('/training');
    }
}
