<?php

namespace App\Helpers;

/**
 * Feeds approved analysis results into the SPC (statistical process control)
 * readings so control charts stay current without manual re-entry.
 */
class SpcHelper
{
    /**
     * Insert an approved numeric result into spc_readings.
     * Matches the spc_parameters record by parameter_code, falling back
     * to a case-insensitive name match. No-op when no SPC parameter matches
     * or the value is not numeric.
     *
     * @param array $sap sample_analysis_parameters row (must include parameter_code/parameter_name)
     * @param string|null $batchNumber
     */
    public static function feed(array $sap, ?string $batchNumber = null): void
    {
        try {
            $db = Database::connect();
            $value = $sap['result_value'] ?? null;
            if ($value === null) {
                return;
            }
            $code = $sap['parameter_code'] ?? null;
            $name = $sap['parameter_name'] ?? null;

            if (!$code && !$name) {
                return;
            }

            $sql = "SELECT id FROM spc_parameters WHERE is_active = TRUE AND (";
            $params = [];
            $or = [];
            if ($code) {
                $or[] = 'LOWER(parameter_code) = LOWER(?)';
                $params[] = $code;
            }
            if ($name) {
                $or[] = 'LOWER(parameter_name) = LOWER(?)';
                $params[] = $name;
            }
            $sql .= implode(' OR ', $or) . ') ORDER BY id LIMIT 1';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $paramId = $stmt->fetchColumn();

            if (!$paramId) {
                return;
            }

            $ins = $db->prepare(
                "INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, entered_by, notes) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?)"
            );
            $ins->execute([
                (int)$paramId,
                $batchNumber,
                (float)$value,
                Auth::id() ?: 1,
                'Auto-fed from analysis result (sample_analysis_parameters #' . ($sap['id'] ?? 0) . ')',
            ]);
        } catch (\Exception $e) {
            error_log("SPC feed error: " . $e->getMessage());
        }
    }
}
