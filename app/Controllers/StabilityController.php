<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class StabilityController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $studies = $db->query("
            SELECT s.*, p.product_name, u.full_name AS created_by_name
            FROM stability_studies s
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.created_by = u.id
            ORDER BY s.created_at DESC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('stability.index', ['studies' => $studies]);
    }

    public function create(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $products = $db->query("SELECT id, product_code, product_name FROM products WHERE is_active = TRUE ORDER BY product_name")->fetchAll(\PDO::FETCH_ASSOC);
        $conditions = ['25°C/60%RH', '30°C/65%RH', '40°C/75%RH', '5°C', '-20°C', 'Light', 'Freeze-Thaw'];
        return $this->render('stability.form', ['study' => null, 'products' => $products, 'conditions' => $conditions]);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO stability_studies (study_name, product_id, batch_number, storage_condition, duration_days, study_type, protocol, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['study_name'],
            $_POST['product_id'] ?: null,
            $_POST['batch_number'] ?? null,
            $_POST['storage_condition'],
            $_POST['duration_days'] ?? 0,
            $_POST['study_type'] ?? 'Real-Time',
            $_POST['protocol'] ?? null,
            Auth::id(),
        ]);
        $studyId = $db->lastInsertId();
        Audit::log('Stability Study Created', 'stability_studies', $studyId);
        session_flash('success', 'Stability study created.');
        $this->redirect('/stability');
    }

    public function show(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT s.*, p.product_name, u.full_name AS created_by_name
            FROM stability_studies s
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.created_by = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $study = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$study) { session_flash('error', 'Study not found.'); $this->redirect('/stability'); }
        $timepoints = $db->prepare("SELECT * FROM stability_timepoints WHERE study_id = ? ORDER BY day");
        $timepoints->execute([$id]);
        $results = $db->prepare("
            SELECT sr.*, st.day, st.condition_label, t.test_name
            FROM stability_results sr
            JOIN stability_timepoints st ON sr.timepoint_id = st.id
            LEFT JOIN tests t ON sr.test_id = t.id
            WHERE st.study_id = ?
            ORDER BY st.day, t.test_name
        ");
        $results->execute([$id]);
        return $this->render('stability.show', [
            'study' => $study,
            'timepoints' => $timepoints->fetchAll(\PDO::FETCH_ASSOC),
            'results' => $results->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    public function edit(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM stability_studies WHERE id = ?");
        $stmt->execute([$id]);
        $study = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$study) { session_flash('error', 'Study not found.'); $this->redirect('/stability'); }
        $products = $db->query("SELECT id, product_code, product_name FROM products WHERE is_active = TRUE ORDER BY product_name")->fetchAll(\PDO::FETCH_ASSOC);
        $conditions = ['25°C/60%RH', '30°C/65%RH', '40°C/75%RH', '5°C', '-20°C', 'Light', 'Freeze-Thaw'];
        return $this->render('stability.form', ['study' => $study, 'products' => $products, 'conditions' => $conditions]);
    }

    public function update(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE stability_studies SET study_name = ?, product_id = ?, batch_number = ?, storage_condition = ?, duration_days = ?, study_type = ?, protocol = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['study_name'],
            $_POST['product_id'] ?: null,
            $_POST['batch_number'] ?? null,
            $_POST['storage_condition'],
            $_POST['duration_days'] ?? 0,
            $_POST['study_type'] ?? 'Real-Time',
            $_POST['protocol'] ?? null,
            $_POST['status'] ?? 'Active',
            $id,
        ]);
        Audit::log('Stability Study Updated', 'stability_studies', $id);
        session_flash('success', 'Stability study updated.');
        $this->redirect('/stability/' . $id);
    }

    public function addTimepoint(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO stability_timepoints (study_id, day, condition_label, expected_date) VALUES (?, ?, ?, ?)")->execute([
            $id,
            $_POST['day'],
            $_POST['condition_label'] ?? 'Default',
            $_POST['expected_date'] ?: null,
        ]);
        Audit::log('Stability Timepoint Added', 'stability_timepoints', null, null, ['study_id' => $id, 'day' => $_POST['day']]);
        session_flash('success', 'Timepoint added.');
        $this->redirect('/stability/' . $id);
    }

    public function addResult(int $timepointId): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO stability_results (timepoint_id, test_id, result_value, unit, tested_by, tested_date) VALUES (?, ?, ?, ?, ?, ?)")->execute([
            $timepointId,
            $_POST['test_id'],
            $_POST['result_value'] ?? null,
            $_POST['unit'] ?? null,
            Auth::id(),
            $_POST['tested_date'] ?? date('Y-m-d'),
        ]);
        Audit::log('Stability Result Added', 'stability_results', null, null, ['timepoint_id' => $timepointId]);
        session_flash('success', 'Result recorded.');
        $this->back();
    }

    public function startTimepoint(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT study_id FROM stability_timepoints WHERE id = ?");
        $stmt->execute([$id]);
        $tp = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$tp) { session_flash('error', 'Timepoint not found.'); $this->redirect('/stability'); }
        $db->prepare("UPDATE stability_timepoints SET status = 'In Progress', started_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        Audit::log('Stability Timepoint Started', 'stability_timepoints', $id);
        session_flash('success', 'Timepoint started.');
        $this->redirect('/stability/' . $tp['study_id']);
    }

    public function completeTimepoint(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT study_id FROM stability_timepoints WHERE id = ?");
        $stmt->execute([$id]);
        $tp = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$tp) { session_flash('error', 'Timepoint not found.'); $this->redirect('/stability'); }
        $db->prepare("UPDATE stability_timepoints SET status = 'Completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        Audit::log('Stability Timepoint Completed', 'stability_timepoints', $id);
        session_flash('success', 'Timepoint completed.');
        $this->redirect('/stability/' . $tp['study_id']);
    }

    public function showResult(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT sr.*, st.day AS timepoint_day, st.condition_label, s.id AS study_id, s.study_code
            FROM stability_results sr
            JOIN stability_timepoints st ON sr.timepoint_id = st.id
            JOIN stability_studies s ON st.study_id = s.id
            WHERE sr.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$result) { session_flash('error', 'Result not found.'); $this->redirect('/stability'); }
        return $this->render('stability.result', ['result' => $result]);
    }

    public function closeStudy(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE stability_studies SET status = 'Closed', closed_date = CURRENT_DATE, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        Audit::log('Stability Study Closed', 'stability_studies', $id);
        session_flash('success', 'Study closed.');
        $this->redirect('/stability');
    }
}
