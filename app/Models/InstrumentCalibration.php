<?php

namespace App\Models;

use App\BaseModel;

class InstrumentCalibration extends BaseModel
{
    protected static string $table = 'instrument_calibrations';
    protected static string $primaryKey = 'id';

    public static function findByInstrument(int $instrumentId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT ic.*, u.full_name AS created_by_name FROM instrument_calibrations ic LEFT JOIN users u ON ic.created_by = u.id WHERE ic.instrument_id = ? ORDER BY ic.calibration_date DESC");
        $stmt->execute([$instrumentId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function upcomingCalibrations(int $days = 30): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT ic.*, i.instrument_name, i.instrument_code FROM instrument_calibrations ic JOIN instruments i ON ic.instrument_id = i.id WHERE ic.next_calibration_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '? days' ORDER BY ic.next_calibration_date");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
