<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class CalibrationEnhancedController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $overdue = $db->query("
            SELECT i.id, i.instrument_code, i.instrument_name, i.next_calibration_date,
                (SELECT COUNT(*) FROM calibration_records cr WHERE cr.instrument_id = i.id) AS record_count
            FROM instruments i
            WHERE i.next_calibration_date IS NOT NULL AND i.next_calibration_date <= CURRENT_DATE
            ORDER BY i.next_calibration_date
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $upcoming = $db->query("
            SELECT i.id, i.instrument_code, i.instrument_name, i.next_calibration_date
            FROM instruments i
            WHERE i.next_calibration_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days'
            ORDER BY i.next_calibration_date
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $stats = [
            'overdue' => count($overdue),
            'upcoming' => count($upcoming),
            'completed_this_month' => (int)$db->query("SELECT COUNT(*) FROM calibration_records WHERE date_trunc('month', calibration_date) = date_trunc('month', CURRENT_DATE)")->fetchColumn(),
            'total_standards' => (int)$db->query("SELECT COUNT(*) FROM calibration_standards")->fetchColumn(),
        ];
        $standards = $db->query("
            SELECT * FROM calibration_standards
            ORDER BY standard_name
            LIMIT 10
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $schedules = $db->query("
            SELECT cs.*, i.instrument_name, i.instrument_code, st.standard_name, u.full_name AS assigned_name
            FROM calibration_schedules cs
            LEFT JOIN instruments i ON cs.instrument_id = i.id
            LEFT JOIN calibration_standards st ON cs.standard_id = st.id
            LEFT JOIN users u ON cs.assigned_to = u.id
            ORDER BY cs.next_due_date
            LIMIT 10
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $records = $db->query("
            SELECT cr.*, i.instrument_name, i.instrument_code, u.full_name AS performed_by_name, st.standard_name
            FROM calibration_records cr
            LEFT JOIN instruments i ON cr.instrument_id = i.id
            LEFT JOIN users u ON cr.performed_by = u.id
            LEFT JOIN calibration_standards st ON cr.standard_id = st.id
            ORDER BY cr.calibration_date DESC
            LIMIT 10
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('calibrations-enhanced.index', [
            'stats' => $stats,
            'standards' => $standards,
            'schedules' => $schedules,
            'records' => $records,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
        ]);
    }

    public function standards(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $standards = $db->query("
            SELECT cs.*
            FROM calibration_standards cs
            ORDER BY cs.standard_name
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('calibrations-enhanced.standards', ['standards' => $standards]);
    }

    public function createStandard(): string
    {
        Auth::requireRole('Admin');
        return $this->render('calibrations-enhanced.standard-form', ['standard' => null]);
    }

    public function editStandard(int $id): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM calibration_standards WHERE id = ?");
        $stmt->execute([$id]);
        $standard = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$standard) { session_flash('error', 'Standard not found.'); $this->redirect('/calibrations/standards'); }
        return $this->render('calibrations-enhanced.standard-form', ['standard' => $standard]);
    }

    public function storeStandard(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO calibration_standards (standard_code, standard_name, standard_type, serial_number, certificate_number, calibration_interval_days, last_calibration_date, next_calibration_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['standard_code'],
            $_POST['standard_name'],
            $_POST['standard_type'] ?? 'Reference',
            $_POST['serial_number'] ?? null,
            $_POST['certificate_number'] ?? null,
            $_POST['calibration_interval_days'] ?: null,
            $_POST['last_calibration_date'] ?? null,
            $_POST['next_calibration_date'] ?? null,
            $_POST['notes'] ?? null,
        ]);
        $stdId = $db->lastInsertId();
        Audit::log('Calibration Standard Created', 'calibration_standards', $stdId);
        session_flash('success', 'Calibration standard created.');
        $this->redirect('/calibrations/standards');
    }

    public function updateStandard(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE calibration_standards SET standard_code=?, standard_name=?, standard_type=?, serial_number=?, certificate_number=?, calibration_interval_days=?, last_calibration_date=?, next_calibration_date=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([
            $_POST['standard_code'],
            $_POST['standard_name'],
            $_POST['standard_type'] ?? 'Reference',
            $_POST['serial_number'] ?? null,
            $_POST['certificate_number'] ?? null,
            $_POST['calibration_interval_days'] ?: null,
            $_POST['last_calibration_date'] ?? null,
            $_POST['next_calibration_date'] ?? null,
            $_POST['notes'] ?? null,
            $id,
        ]);
        Audit::log('Calibration Standard Updated', 'calibration_standards', $id);
        session_flash('success', 'Calibration standard updated.');
        $this->redirect('/calibrations/standards');
    }

    public function schedules(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $schedules = $db->query("
            SELECT cs.*, i.instrument_name, i.instrument_code, st.standard_name, u.full_name AS assigned_name
            FROM calibration_schedules cs
            LEFT JOIN instruments i ON cs.instrument_id = i.id
            LEFT JOIN calibration_standards st ON cs.standard_id = st.id
            LEFT JOIN users u ON cs.assigned_to = u.id
            ORDER BY cs.next_due_date
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $instruments = $db->query("SELECT id, instrument_code, instrument_name FROM instruments WHERE is_active = TRUE ORDER BY instrument_name")->fetchAll(\PDO::FETCH_ASSOC);
        $standards = $db->query("SELECT id, standard_code, standard_name FROM calibration_standards ORDER BY standard_name")->fetchAll(\PDO::FETCH_ASSOC);
        $users = $db->query("SELECT id, username, full_name FROM users ORDER BY full_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('calibrations-enhanced.schedules', [
            'schedules' => $schedules,
            'instruments' => $instruments,
            'standards' => $standards,
            'users' => $users,
        ]);
    }

    public function createSchedule(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $instruments = $db->query("SELECT id, instrument_code, instrument_name FROM instruments WHERE is_active = TRUE ORDER BY instrument_name")->fetchAll(\PDO::FETCH_ASSOC);
        $standards = $db->query("SELECT id, standard_code, standard_name FROM calibration_standards ORDER BY standard_name")->fetchAll(\PDO::FETCH_ASSOC);
        $users = $db->query("SELECT id, username, full_name FROM users ORDER BY full_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('calibrations-enhanced.schedules', ['schedules' => [], 'instruments' => $instruments, 'standards' => $standards, 'users' => $users]);
    }

    public function storeSchedule(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO calibration_schedules (instrument_id, standard_id, frequency_days, last_due_date, next_due_date, assigned_to) VALUES (?, ?, ?, ?, ?, ?)")->execute([
            $_POST['instrument_id'],
            $_POST['standard_id'] ?: null,
            $_POST['frequency_days'] ?? 365,
            $_POST['last_due_date'] ?? null,
            $_POST['next_due_date'] ?? null,
            $_POST['assigned_to'] ?: null,
        ]);
        Audit::log('Calibration Schedule Created', 'calibration_schedules', null, null, ['instrument_id' => $_POST['instrument_id']]);
        session_flash('success', 'Schedule created.');
        $this->redirect('/calibrations/schedules');
    }

    public function records(int $instrumentId): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM instruments WHERE id = ?");
        $stmt->execute([$instrumentId]);
        $instrument = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$instrument) { session_flash('error', 'Instrument not found.'); $this->redirect('/calibrations'); }
        $records = $db->prepare("
            SELECT cr.*, u.full_name AS performed_by_name, cs.standard_name
            FROM calibration_records cr
            LEFT JOIN users u ON cr.performed_by = u.id
            LEFT JOIN calibration_standards cs ON cr.standard_id = cs.id
            WHERE cr.instrument_id = ?
            ORDER BY cr.calibration_date DESC
            LIMIT 100
        ");
        $records->execute([$instrumentId]);
        $standards = $db->query("SELECT id, standard_name FROM calibration_standards WHERE status = 'Active' ORDER BY standard_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('calibrations-enhanced.records', [
            'instrument' => $instrument,
            'records' => $records->fetchAll(\PDO::FETCH_ASSOC),
            'standards' => $standards,
        ]);
    }

    public function addRecord(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO calibration_records (instrument_id, calibration_date, calibrated_by, standard_id, result, certificate_number, next_calibration_date, notes, performed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['instrument_id'],
            $_POST['calibration_date'],
            $_POST['calibrated_by'] ?? null,
            $_POST['standard_id'] ?: null,
            $_POST['result'] ?? 'Pass',
            $_POST['certificate_number'] ?? null,
            $_POST['next_calibration_date'] ?? null,
            $_POST['notes'] ?? null,
            Auth::id(),
        ]);
        $db->prepare("UPDATE instruments SET last_calibration_date = ?, next_calibration_date = ? WHERE id = ?")->execute([
            $_POST['calibration_date'],
            $_POST['next_calibration_date'] ?? null,
            $_POST['instrument_id'],
        ]);
        Audit::log('Calibration Record Added', 'calibration_records', null, null, ['instrument_id' => $_POST['instrument_id']]);
        session_flash('success', 'Calibration record added.');
        $this->redirect('/calibrations/records/' . $_POST['instrument_id']);
    }

    public function getOverdue(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $overdue = $db->query("
            SELECT i.id, i.instrument_code, i.instrument_name, i.next_calibration_date,
                (CURRENT_DATE - i.next_calibration_date) AS days_overdue
            FROM instruments i
            WHERE i.next_calibration_date IS NOT NULL AND i.next_calibration_date < CURRENT_DATE
            ORDER BY days_overdue DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->json($overdue);
    }
}
