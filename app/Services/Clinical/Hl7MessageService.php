<?php

namespace App\Services\Clinical;

class Hl7MessageService
{
    private string $fieldSep = '|';
    private string $compSep = '^';
    private string $repSep = '~';
    private string $escapeChar = '\\';
    private string $subCompSep = '&';

    /**
     * Generate HL7 v2.5.1 ORU^R01 message string
     *
     * @param array<string, mixed> $sample Order / Specimen metadata
     * @param array<int, array<string, mixed>> $results Test results
     * @param array<string, mixed> $patient Patient or Subject info
     * @return string HL7 v2 pipe-delimited message with \r segment terminators
     */
    public function generateOruR01(array $sample, array $results, array $patient = []): string
    {
        $timestamp = date('YmdHis');
        $msgControlId = 'MSG-' . date('Ymd') . '-' . substr(uniqid(), -6);

        $segments = [];

        // MSH - Message Header
        $segments[] = implode($this->fieldSep, [
            'MSH',
            '^~\\&',
            'PLEXIQ_LIMS',
            'MAIN_LAB',
            'HIS_LIS',
            'HOSPITAL',
            $timestamp,
            '',
            'ORU^R01^ORU_R01',
            $msgControlId,
            'P', // Production
            '2.5.1'
        ]);

        // PID - Patient Identification
        $patientId = $patient['patient_id'] ?? ($sample['client_id'] ?? 'PID-UNKNOWN');
        $patientName = ($patient['last_name'] ?? 'Doe') . '^' . ($patient['first_name'] ?? 'John');
        $dob = $patient['dob'] ?? '19850101';
        $gender = $patient['gender'] ?? 'U';

        $segments[] = implode($this->fieldSep, [
            'PID',
            '1',
            $patientId,
            $patientId . '^^^PLEXIQ^MR',
            '',
            $patientName,
            '',
            $dob,
            $gender,
            '',
            '',
            $patient['address'] ?? '100 Healthcare Blvd^^Metropolis^NY^10001'
        ]);

        // OBR - Observation Request
        $orderNumber = $sample['sample_code'] ?? ('ORD-' . time());
        $universalServiceId = 'LAB100^Laboratory Panel^L';
        $sampleDate = isset($sample['created_at']) ? date('YmdHis', strtotime($sample['created_at'])) : $timestamp;

        $segments[] = implode($this->fieldSep, [
            'OBR',
            '1',
            $orderNumber,
            $orderNumber,
            $universalServiceId,
            '',
            '',
            $sampleDate,
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'SER^Serum^HL70070', // Specimen source
            '',
            '',
            '',
            '',
            '',
            '',
            $timestamp,
            'F' // Result status: Final
        ]);

        // OBX - Observation Results
        $seq = 1;
        foreach ($results as $res) {
            $loinc = $res['loinc_code'] ?? '14682-9';
            $testName = $res['test_name'] ?? ('Test ' . $seq);
            $val = (string)($res['result_value'] ?? $res['numeric_value'] ?? '');
            $unit = $res['unit'] ?? '';
            $isNumeric = is_numeric($val);
            $valType = $isNumeric ? 'NM' : 'ST';
            $refRange = '';
            if (isset($res['min_limit']) || isset($res['max_limit'])) {
                $refRange = ($res['min_limit'] ?? '') . '-' . ($res['max_limit'] ?? '');
            }
            $abnormalFlag = ($res['status'] ?? 'pass') === 'pass' ? 'N' : 'A';

            $segments[] = implode($this->fieldSep, [
                'OBX',
                (string)$seq,
                $valType,
                $loinc . '^' . $testName . '^LN',
                '1',
                $val,
                $unit,
                $refRange,
                $abnormalFlag,
                '',
                '',
                'F', // Result status
                '',
                '',
                $timestamp,
                'PLEXIQ_LAB'
            ]);

            // Optional NTE notes segment
            if (!empty($res['notes'])) {
                $segments[] = implode($this->fieldSep, [
                    'NTE',
                    '1',
                    'L',
                    $res['notes']
                ]);
            }

            $seq++;
        }

        return implode("\r", $segments) . "\r";
    }

    /**
     * Parse an incoming HL7 v2 string message into structured segment arrays
     *
     * @param string $hl7Raw
     * @return array<string, mixed>
     */
    public function parseMessage(string $hl7Raw): array
    {
        $lines = preg_split('/[\r\n]+/', trim($hl7Raw));
        if (empty($lines)) {
            return ['success' => false, 'error' => 'Empty HL7 message.'];
        }

        $parsed = [
            'msh' => [],
            'pid' => [],
            'obr' => [],
            'obx' => [],
            'nte' => []
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $fields = explode($this->fieldSep, $line);
            $segName = strtolower($fields[0]);

            if ($segName === 'msh') {
                $parsed['msh'] = [
                    'sending_app' => $fields[2] ?? '',
                    'sending_facility' => $fields[3] ?? '',
                    'receiving_app' => $fields[4] ?? '',
                    'receiving_facility' => $fields[5] ?? '',
                    'timestamp' => $fields[6] ?? '',
                    'message_type' => $fields[8] ?? '',
                    'control_id' => $fields[9] ?? '',
                    'version' => $fields[11] ?? ''
                ];
            } elseif ($segName === 'pid') {
                $rawPid = $fields[3] ?? ($fields[2] ?? '');
                $cleanPid = explode('^', $rawPid)[0];
                $parsed['pid'] = [
                    'patient_id' => !empty($cleanPid) ? $cleanPid : ($fields[2] ?? ''),
                    'name' => $fields[5] ?? '',
                    'dob' => $fields[7] ?? '',
                    'gender' => $fields[8] ?? ''
                ];
            } elseif ($segName === 'obr') {
                $parsed['obr'] = [
                    'placer_order_number' => $fields[2] ?? '',
                    'filler_order_number' => $fields[3] ?? '',
                    'service_id' => $fields[4] ?? '',
                    'observation_date' => $fields[7] ?? '',
                    'status' => $fields[24] ?? ''
                ];
            } elseif ($segName === 'obx') {
                $parsed['obx'][] = [
                    'seq' => $fields[1] ?? '',
                    'value_type' => $fields[2] ?? '',
                    'observation_id' => $fields[3] ?? '',
                    'value' => $fields[5] ?? '',
                    'units' => $fields[6] ?? '',
                    'ref_range' => $fields[7] ?? '',
                    'abnormal_flag' => $fields[8] ?? '',
                    'status' => $fields[11] ?? ''
                ];
            } elseif ($segName === 'nte') {
                $parsed['nte'][] = [
                    'seq' => $fields[1] ?? '',
                    'comment' => $fields[3] ?? ''
                ];
            }
        }

        return [
            'success' => true,
            'segments' => $parsed
        ];
    }
}
