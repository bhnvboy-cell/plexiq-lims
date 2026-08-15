<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class EnvironmentalController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $points = $db->query("
            SELECT ep.*,
                (SELECT COUNT(*) FROM environmental_readings er WHERE er.point_id = ep.id) AS reading_count,
                (SELECT reading_value FROM environmental_readings er WHERE er.point_id = ep.id ORDER BY er.created_at DESC LIMIT 1) AS last_reading_value,
                (SELECT created_at FROM environmental_readings er WHERE er.point_id = ep.id ORDER BY er.created_at DESC LIMIT 1) AS last_reading_time
            FROM environmental_points ep
            ORDER BY ep.location_name
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $alerts = $db->query("
            SELECT ea.*, ep.point_name, ep.location_name, ep.monitoring_type, u.full_name AS resolved_by_name
            FROM environmental_alerts ea
            JOIN environmental_points ep ON ea.point_id = ep.id
            LEFT JOIN users u ON ea.resolved_by = u.id
            WHERE ea.is_resolved = FALSE
            ORDER BY ea.created_at DESC
            LIMIT 20
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('environmental.index', ['points' => $points, 'alerts' => $alerts]);
    }

    public function points(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $points = $db->query("SELECT * FROM environmental_points ORDER BY location_name, point_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('environmental.points', ['points' => $points]);
    }

    public function createPoint(): string
    {
        Auth::requireAuth();
        return $this->render('environmental.point-form', ['point' => null]);
    }

    public function storePoint(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO environmental_points (point_name, location_name, monitoring_type, unit, min_threshold, max_threshold, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['point_name'],
            $_POST['location_name'],
            $_POST['monitoring_type'] ?? 'Temperature',
            $_POST['unit'] ?? '°C',
            $_POST['min_threshold'] ?: null,
            $_POST['max_threshold'] ?: null,
            !empty($_POST['is_active']),
        ]);
        $pointId = $db->lastInsertId();
        Audit::log('Environmental Point Created', 'environmental_points', $pointId);
        session_flash('success', 'Monitoring point created.');
        $this->redirect('/environmental/points');
    }

    public function readings(int $pointId): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM environmental_points WHERE id = ?");
        $stmt->execute([$pointId]);
        $point = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$point) { session_flash('error', 'Point not found.'); $this->redirect('/environmental'); }
        $result = \App\Helpers\Pagination::run($db, "
            SELECT er.*, u.full_name AS recorded_by_name
            FROM environmental_readings er
            LEFT JOIN users u ON er.recorded_by = u.id
            WHERE er.point_id = {$pointId}
        ", "
            SELECT COUNT(*)
            FROM environmental_readings er
            WHERE er.point_id = {$pointId}
        ", [], 50, 'er.created_at DESC');
        $points = $db->query("SELECT id, point_name, location_name FROM environmental_points ORDER BY location_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('environmental.readings', ['point' => $point, 'points' => $points, 'readings' => $result['items'], 'paginator' => $result, 'selectedPointId' => $pointId]);
    }

    public function addReading(int $pointId): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO environmental_readings (point_id, reading_value, unit, recorded_by, notes) VALUES (?, ?, ?, ?, ?)")->execute([
            $pointId,
            $_POST['reading_value'],
            $_POST['unit'] ?? '°C',
            Auth::id(),
            $_POST['notes'] ?? null,
        ]);
        // Check thresholds and create alert if violated
        $stmt = $db->prepare("SELECT * FROM environmental_points WHERE id = ?");
        $stmt->execute([$pointId]);
        $point = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($point && $point['min_threshold'] !== null && (float)$_POST['reading_value'] < (float)$point['min_threshold']) {
            $db->prepare("INSERT INTO environmental_alerts (point_id, alert_type, reading_value, threshold_value, message) VALUES (?, 'min_violation', ?, ?, ?)")->execute([
                $pointId, $_POST['reading_value'], $point['min_threshold'], 'Below minimum threshold',
            ]);
        }
        if ($point && $point['max_threshold'] !== null && (float)$_POST['reading_value'] > (float)$point['max_threshold']) {
            $db->prepare("INSERT INTO environmental_alerts (point_id, alert_type, reading_value, threshold_value, message) VALUES (?, 'max_violation', ?, ?, ?)")->execute([
                $pointId, $_POST['reading_value'], $point['max_threshold'], 'Exceeded maximum threshold',
            ]);
        }
        Audit::log('Environmental Reading Added', 'environmental_readings', null, null, ['point_id' => $pointId, 'value' => $_POST['reading_value']]);
        session_flash('success', 'Reading recorded.');
        $this->redirect('/environmental/points/' . $pointId . '/readings');
    }

    public function acknowledgeAlert(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE environmental_alerts SET is_resolved = TRUE, resolved_by = ?, resolved_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([Auth::id(), $id]);
        Audit::log('Alert Acknowledged', 'environmental_alerts', $id);
        session_flash('success', 'Alert acknowledged.');
        $this->redirect('/environmental');
    }

    public function alerts(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $result = \App\Helpers\Pagination::run($db, "
            SELECT ea.*, ep.point_name, ep.location_name, ep.monitoring_type, u.full_name AS resolved_by_name
            FROM environmental_alerts ea
            JOIN environmental_points ep ON ea.point_id = ep.id
            LEFT JOIN users u ON ea.resolved_by = u.id
        ", "
            SELECT COUNT(*)
            FROM environmental_alerts ea
            JOIN environmental_points ep ON ea.point_id = ep.id
        ", [], 50, 'ea.created_at DESC');
        return $this->render('environmental.alerts', ['alerts' => $result['items'], 'paginator' => $result]);
    }
}
