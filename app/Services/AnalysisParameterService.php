<?php

namespace App\Services;

use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Helpers\Database;
use App\Helpers\JobQueue;
use App\Helpers\SpcHelper;

/**
 * Core logic for the Analysis Parameter Management module:
 * parameter assignment, spec validation, result workflow,
 * automatic OOS detection and SPC feed.
 */
class AnalysisParameterService
{
    public function assignToSample(int $sampleId, array $parameterIds, ?int $analystId = null): array
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $inserted = 0;
            $stmt = $db->prepare("
                INSERT INTO sample_analysis_parameters (sample_id, parameter_id, spec_min, spec_max, spec_target, unit)
                SELECT ?, ap.id, ap.spec_min, ap.spec_max, ap.spec_target, ap.unit
                FROM analysis_parameters ap
                WHERE ap.id = ? AND ap.is_active = TRUE
                ON CONFLICT (sample_id, parameter_id) DO NOTHING
            ");
            foreach ($parameterIds as $parameterId) {
                $stmt->execute([$sampleId, (int)$parameterId]);
                $inserted += $stmt->rowCount();
            }
            $db->commit();
            Audit::log('Analysis Parameters Assigned', 'samples', $sampleId, null, [
                'parameters' => array_map('intval', $parameterIds),
            ]);
            return ['inserted' => $inserted];
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function assignToBatch(string $batchNumber, array $parameterIds, ?int $productId = null): array
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $inserted = 0;
            $stmt = $db->prepare("
                INSERT INTO batch_analysis_parameters (batch_number, product_id, parameter_id, spec_min, spec_max, spec_target, unit)
                SELECT ?, ?, ap.id, ap.spec_min, ap.spec_max, ap.spec_target, ap.unit
                FROM analysis_parameters ap
                WHERE ap.id = ? AND ap.is_active = TRUE
                ON CONFLICT (batch_number, parameter_id) DO UPDATE
                    SET spec_min = EXCLUDED.spec_min, spec_max = EXCLUDED.spec_max,
                        spec_target = EXCLUDED.spec_target, unit = EXCLUDED.unit,
                        updated_at = CURRENT_TIMESTAMP
            ");
            foreach ($parameterIds as $parameterId) {
                $stmt->execute([$batchNumber, $productId, (int)$parameterId]);
                $inserted += $stmt->rowCount();
            }
            $db->commit();
            return ['inserted' => $inserted];
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Validate a numeric value against spec limits.
     * Returns ['within_spec' => ?bool, 'reason' => ?string].
     */
    public function validateValue(array $parameter, $value): array
    {
        if ($value === null || $value === '') {
            return ['within_spec' => null, 'reason' => null];
        }
        $min = $parameter['spec_min'] ?? null;
        $max = $parameter['spec_max'] ?? null;
        $value = (float)$value;

        if ($min !== null && $max !== null) {
            $ok = $value >= (float)$min && $value <= (float)$max;
            $reason = $ok ? null : sprintf('Value %.4f outside spec range [%s - %s]', $value, $min, $max);
            return ['within_spec' => $ok, 'reason' => $reason];
        }
        if ($min !== null) {
            $ok = $value >= (float)$min;
            return ['within_spec' => $ok, 'reason' => $ok ? null : sprintf('Value %.4f below min spec %s', $value, $min)];
        }
        if ($max !== null) {
            $ok = $value <= (float)$max;
            return ['within_spec' => $ok, 'reason' => $ok ? null : sprintf('Value %.4f above max spec %s', $value, $max)];
        }
        return ['within_spec' => null, 'reason' => null];
    }

    public function recordResult(int $sampleAnalysisParameterId, $value, ?string $resultText = null, ?string $notes = null, string $source = 'manual'): array
    {
        $db = Database::connect();
        $row = $db->prepare("
            SELECT sap.*, ap.parameter_code, ap.parameter_name, ap.specification_text
            FROM sample_analysis_parameters sap
            JOIN analysis_parameters ap ON sap.parameter_id = ap.id
            WHERE sap.id = ?
        ");
        $row->execute([$sampleAnalysisParameterId]);
        $sap = $row->fetch(\PDO::FETCH_ASSOC);
        if (!$sap) {
            throw new \RuntimeException('Sample analysis parameter not found.');
        }

        $numericValue = null;
        if ($value !== null && $value !== '' && is_numeric($value)) {
            $numericValue = (float)$value;
        } elseif ($value !== null && $value !== '') {
            $resultText = $value;
        }

        $validation = $this->validateValue($sap, $numericValue);
        $withinSpec = $validation['within_spec'] === null ? null : ($validation['within_spec'] ? 't' : 'f');

        $db->beginTransaction();
        try {
            $update = $db->prepare("
                UPDATE sample_analysis_parameters
                SET result_value = ?, result_text = ?, is_within_spec = ?, analyst_notes = ?,
                    status = ?, entered_by = ?, entered_at = CURRENT_TIMESTAMP,
                    source = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update->execute([
                $numericValue,
                $resultText,
                $withinSpec,
                $notes,
                'Completed',
                Auth::id() ?: 1,
                $source,
                $sampleAnalysisParameterId,
            ]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        Audit::log('Analysis Result Recorded', 'sample_analysis_parameters', $sampleAnalysisParameterId, null, [
            'value' => $numericValue,
            'text' => $resultText,
            'within_spec' => $validation['within_spec'],
            'source' => $source,
        ]);

        $sap['result_value'] = $numericValue;
        $sap['result_text'] = $resultText;
        $sap['is_within_spec'] = $validation['within_spec'];

        if ($validation['within_spec'] === false) {
            $this->createOosRecord($sap, $validation['reason']);
        }

        return $sap;
    }

    public function review(int $sampleAnalysisParameterId): void
    {
        $db = Database::connect();
        $row = $db->prepare("
            SELECT sap.*, s.sample_code, s.batch_number, ap.parameter_code, ap.parameter_name
            FROM sample_analysis_parameters sap
            JOIN samples s ON sap.sample_id = s.id
            JOIN analysis_parameters ap ON sap.parameter_id = ap.id
            WHERE sap.id = ?
        ");
        $row->execute([$sampleAnalysisParameterId]);
        $sap = $row->fetch(\PDO::FETCH_ASSOC);
        if (!$sap) {
            throw new \RuntimeException('Sample analysis parameter not found.');
        }
        if (!in_array($sap['status'], ['Completed', 'Reviewed'], true)) {
            throw new \RuntimeException('Only completed results can be reviewed.');
        }
        $db->prepare("
            UPDATE sample_analysis_parameters
            SET status = 'Reviewed', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([Auth::id() ?: 1, $sampleAnalysisParameterId]);
        Audit::log('Analysis Result Reviewed', 'sample_analysis_parameters', $sampleAnalysisParameterId);
    }

    public function approve(int $sampleAnalysisParameterId): void
    {
        $db = Database::connect();
        $row = $db->prepare("
            SELECT sap.*, s.sample_code, s.batch_number, ap.parameter_code, ap.parameter_name
            FROM sample_analysis_parameters sap
            JOIN samples s ON sap.sample_id = s.id
            JOIN analysis_parameters ap ON sap.parameter_id = ap.id
            WHERE sap.id = ?
        ");
        $row->execute([$sampleAnalysisParameterId]);
        $sap = $row->fetch(\PDO::FETCH_ASSOC);
        if (!$sap) {
            throw new \RuntimeException('Sample analysis parameter not found.');
        }
        if ($sap['status'] !== 'Reviewed') {
            throw new \RuntimeException('Only reviewed results can be approved.');
        }
        $db->prepare("
            UPDATE sample_analysis_parameters
            SET status = 'Approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([Auth::id() ?: 1, $sampleAnalysisParameterId]);
        Audit::log('Analysis Result Approved', 'sample_analysis_parameters', $sampleAnalysisParameterId);

        if ($sap['result_value'] !== null) {
            SpcHelper::feed($sap, $sap['batch_number']);
        }
    }

    /**
     * Auto-create an OOS record for an out-of-spec result.
     */
    public function createOosRecord(array $sap, ?string $reason): void
    {
        $db = Database::connect();
        $next = $this->nextOosNumber();
        $sampleId = (int)($sap['sample_id'] ?? 0);

        try {
            $stmt = $db->prepare("
                INSERT INTO oos_records
                    (oos_number, sample_id, test_parameter, specification_range, result_value, result_text, unit, description, severity, status, initiated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Major', 'Open', ?)
                ON CONFLICT (oos_number) DO NOTHING
            ");
            $range = trim(($sap['spec_min'] ?? '') . ' - ' . ($sap['spec_max'] ?? ''));
            $paramName = ($sap['parameter_name'] ?? $sap['parameter_code'] ?? 'Parameter');
            $stmt->execute([
                $next,
                $sampleId ?: null,
                $paramName,
                $range !== ' - ' ? $range : ($sap['specification_text'] ?? null),
                $sap['result_value'] ?? null,
                $sap['result_text'] ?? null,
                $sap['unit'] ?? null,
                $reason ?: 'Result is out of specification.',
                Auth::id() ?: 1,
            ]);
            Audit::log('OOS Auto-Triggered', 'oos_records', null, null, [
                'sample_analysis_parameter' => $sap['id'] ?? null,
                'parameter' => $paramName,
                'reason' => $reason,
            ]);
            $this->notifyOos($sampleId, $paramName);
        } catch (\Exception $e) {
            error_log("Auto-OOS error: " . $e->getMessage());
        }
    }

    private function nextOosNumber(): string
    {
        $db = Database::connect();
        $max = (int)$db->query("SELECT COALESCE(MAX(CAST(substring(oos_number from 'OOS-([0-9]+)') AS INTEGER)), 0) FROM oos_records")->fetchColumn();
        return 'OOS-' . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
    }

    private function notifyOos(int $sampleId, string $parameter): void
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("SELECT sample_code FROM samples WHERE id = ?");
            $stmt->execute([$sampleId]);
            $label = $stmt->fetchColumn() ?: ('sample #' . $sampleId);
            \notify_role('Reviewer', 'oos_created', 'OOS Detected: ' . $parameter,
                'Out-of-specification result recorded for ' . $label . ' (' . $parameter . ').', '/oos');
            \notify_role('Approver', 'oos_created', 'OOS Detected: ' . $parameter,
                'Out-of-specification result recorded for ' . $label . ' (' . $parameter . ').', '/oos');
        } catch (\Exception $e) {
            error_log("OOS notify error: " . $e->getMessage());
        }
    }

    public function pendingResultCounts(): array
    {
        $db = Database::connect();
        $role = Auth::role() ?? '';
        $where = match ($role) {
            'Reviewer' => "WHERE status = 'Completed'",
            'Approver' => "WHERE status = 'Reviewed'",
            default => '',
        };
        $stmt = $db->query("SELECT COUNT(*) FROM sample_analysis_parameters {$where}");
        return ['pending' => (int)$stmt->fetchColumn(), 'role' => $role];
    }

    public function importFromWatchDirectories(): array
    {
        $db = Database::connect();
        $instruments = $db->query("
            SELECT * FROM instruments WHERE auto_import = TRUE AND file_watch_path IS NOT NULL AND file_watch_path != '' AND is_active = TRUE
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $dispatched = 0;
        foreach ($instruments as $instrument) {
            $path = $instrument['file_watch_path'];
            if (!is_dir($path)) {
                continue;
            }
            $supported = match (strtoupper($instrument['interface_type'])) {
                'CSV' => ['csv', 'tsv'],
                'XML' => ['xml'],
                'TEXT', 'TXT' => ['txt', 'dat', 'prn'],
                default => [],
            };
            foreach (glob($path . DIRECTORY_SEPARATOR . '*.*') ?: [] as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $supported, true)) {
                    continue;
                }
                if ($this->fileAlreadyImported($file)) {
                    continue;
                }
                JobQueue::dispatch(\App\Jobs\ImportInstrumentFile::class, [
                    'instrument_id' => (int)$instrument['id'],
                    'file_path' => $file,
                    'original_name' => basename($file),
                ], 'imports');
                $dispatched++;
            }
        }
        return ['dispatched' => $dispatched];
    }

    public function fileAlreadyImported(string $filePath): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT COUNT(*) FROM instrument_results WHERE source_file = ?");
        $stmt->execute([basename($filePath)]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
