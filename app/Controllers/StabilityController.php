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
            SELECT s.*, p.product_name, b.batch_number, u.full_name AS created_by_name
            FROM stability_studies s
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN batches b ON s.batch_id = b.id
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
        $batches = $db->query("SELECT b.id, b.batch_number, p.product_name FROM batches b LEFT JOIN products p ON b.product_id = p.id ORDER BY b.batch_number")->fetchAll(\PDO::FETCH_ASSOC);
        $studyTypes = ['Long Term', 'Accelerated', 'Intermediate', 'Real-Time'];
        return $this->render('stability.form', ['study' => null, 'products' => $products, 'batches' => $batches, 'studyTypes' => $studyTypes]);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO stability_studies (study_code, study_name, product_id, batch_id, study_type, condition_temperature, condition_humidity, condition_light, storage_condition, protocol_ref, status, start_date, end_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['study_code'],
            $_POST['study_name'],
            $_POST['product_id'] ?? null,
            $_POST['batch_id'] ?? null,
            $_POST['study_type'] ?? 'Long Term',
            ($_POST['condition_temperature'] ?? '') !== '' ? $_POST['condition_temperature'] : null,
            ($_POST['condition_humidity'] ?? '') !== '' ? $_POST['condition_humidity'] : null,
            $_POST['condition_light'] ?? null,
            $_POST['storage_condition'] ?? null,
            $_POST['protocol_ref'] ?? null,
            $_POST['status'] ?? 'Active',
            ($_POST['start_date'] ?? '') !== '' ? $_POST['start_date'] : null,
            ($_POST['end_date'] ?? '') !== '' ? $_POST['end_date'] : null,
            Auth::id(),
        ]);
        $studyId = $db->lastInsertId();
        Audit::log('Stability Study Created', 'stability_studies', $studyId);
        session_flash('success', 'Stability study created.');
        $this->redirect('/stability/' . $studyId);
    }

    public function show(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT s.*, p.product_name, b.batch_number, u.full_name AS created_by_name
            FROM stability_studies s
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN batches b ON s.batch_id = b.id
            LEFT JOIN users u ON s.created_by = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $study = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$study) { session_flash('error', 'Study not found.'); $this->redirect('/stability'); }
        $timepoints = $db->prepare("SELECT * FROM stability_study_timepoints WHERE study_id = ? ORDER BY day_offset, sort_order");
        $timepoints->execute([$id]);
        $results = $db->prepare("
            SELECT sr.*, st.day_offset, st.timepoint_label, t.test_name, u.full_name AS tested_by_name
            FROM stability_study_results sr
            JOIN stability_study_timepoints st ON sr.timepoint_id = st.id
            LEFT JOIN tests t ON sr.test_id = t.id
            LEFT JOIN users u ON sr.tested_by = u.id
            WHERE st.study_id = ?
            ORDER BY st.day_offset, t.test_name
        ");
        $results->execute([$id]);
        $tests = $db->query("SELECT id, test_code, test_name, spec_limit_text FROM tests WHERE is_active = TRUE ORDER BY test_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('stability.show', [
            'study' => $study,
            'timepoints' => $timepoints->fetchAll(\PDO::FETCH_ASSOC),
            'results' => $results->fetchAll(\PDO::FETCH_ASSOC),
            'tests' => $tests,
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
        $batches = $db->query("SELECT b.id, b.batch_number, p.product_name FROM batches b LEFT JOIN products p ON b.product_id = p.id ORDER BY b.batch_number")->fetchAll(\PDO::FETCH_ASSOC);
        $studyTypes = ['Long Term', 'Accelerated', 'Intermediate', 'Real-Time'];
        return $this->render('stability.form', ['study' => $study, 'products' => $products, 'batches' => $batches, 'studyTypes' => $studyTypes]);
    }

    public function update(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE stability_studies SET study_code = ?, study_name = ?, product_id = ?, batch_id = ?, study_type = ?, condition_temperature = ?, condition_humidity = ?, condition_light = ?, storage_condition = ?, protocol_ref = ?, status = ?, start_date = ?, end_date = ?, report_conclusion = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['study_code'],
            $_POST['study_name'],
            $_POST['product_id'] ?? null,
            $_POST['batch_id'] ?? null,
            $_POST['study_type'] ?? 'Long Term',
            ($_POST['condition_temperature'] ?? '') !== '' ? $_POST['condition_temperature'] : null,
            ($_POST['condition_humidity'] ?? '') !== '' ? $_POST['condition_humidity'] : null,
            $_POST['condition_light'] ?? null,
            $_POST['storage_condition'] ?? null,
            $_POST['protocol_ref'] ?? null,
            $_POST['status'] ?? 'Active',
            ($_POST['start_date'] ?? '') !== '' ? $_POST['start_date'] : null,
            ($_POST['end_date'] ?? '') !== '' ? $_POST['end_date'] : null,
            $_POST['report_conclusion'] ?? null,
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
        $db->prepare("INSERT INTO stability_study_timepoints (study_id, timepoint_label, day_offset, scheduled_date, notes, sort_order) VALUES (?, ?, ?, ?, ?, ?)")->execute([
            $id,
            $_POST['timepoint_label'],
            $_POST['day_offset'],
            $_POST['scheduled_date'] ?: null,
            $_POST['notes'] ?? null,
            $_POST['sort_order'] ?? 0,
        ]);
        Audit::log('Stability Timepoint Added', 'stability_study_timepoints', $db->lastInsertId(), null, ['study_id' => $id]);
        session_flash('success', 'Timepoint added.');
        $this->redirect('/stability/' . $id);
    }

    public function addResult(int $timepointId): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO stability_study_results (timepoint_id, test_id, result_value, specification_limit, result_status, tested_by, tested_at) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([
            $timepointId,
            $_POST['test_id'],
            $_POST['result_value'] ?? null,
            $_POST['specification_limit'] ?? null,
            $_POST['result_status'] ?? 'Pending',
            Auth::id(),
            ($_POST['tested_at'] ?? '') !== '' ? str_replace('T', ' ', $_POST['tested_at']) : date('Y-m-d H:i:s'),
        ]);
        Audit::log('Stability Result Added', 'stability_study_results', $db->lastInsertId(), null, ['timepoint_id' => $timepointId]);
        session_flash('success', 'Result recorded.');
        $this->back();
    }

    public function startTimepoint(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT study_id FROM stability_study_timepoints WHERE id = ?");
        $stmt->execute([$id]);
        $tp = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$tp) { session_flash('error', 'Timepoint not found.'); $this->redirect('/stability'); }
        $db->prepare("UPDATE stability_study_timepoints SET status = 'In Progress' WHERE id = ?")->execute([$id]);
        Audit::log('Stability Timepoint Started', 'stability_study_timepoints', $id);
        session_flash('success', 'Timepoint started.');
        $this->redirect('/stability/' . $tp['study_id']);
    }

    public function completeTimepoint(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT study_id FROM stability_study_timepoints WHERE id = ?");
        $stmt->execute([$id]);
        $tp = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$tp) { session_flash('error', 'Timepoint not found.'); $this->redirect('/stability'); }
        $db->prepare("UPDATE stability_study_timepoints SET status = 'Completed', completed_date = CURRENT_DATE WHERE id = ?")->execute([$id]);
        Audit::log('Stability Timepoint Completed', 'stability_study_timepoints', $id);
        session_flash('success', 'Timepoint completed.');
        $this->redirect('/stability/' . $tp['study_id']);
    }

    public function showResult(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT sr.*, st.timepoint_label, st.day_offset, s.id AS study_id, s.study_code, s.study_name, t.test_name, t.spec_limit_text, u.full_name AS tested_by_name
            FROM stability_study_results sr
            JOIN stability_study_timepoints st ON sr.timepoint_id = st.id
            JOIN stability_studies s ON st.study_id = s.id
            LEFT JOIN tests t ON sr.test_id = t.id
            LEFT JOIN users u ON sr.tested_by = u.id
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
        $db->prepare("UPDATE stability_studies SET status = 'Closed', end_date = CURRENT_DATE, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        Audit::log('Stability Study Closed', 'stability_studies', $id);
        session_flash('success', 'Study closed.');
        $this->redirect('/stability');
    }
}
