<?php
/**
 * PlexiQ LIMS - Beta Test Data Seeder
 * Seeds ~100 customers with samples, batches, results, and data across all modules.
 * Run from project root: php beta/seed_beta_data.php
 */

require __DIR__ . '/../vendor/autoload.php';

// Load environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Load helpers
require_once __DIR__ . '/../app/Helpers/helpers.php';

$db = \App\Helpers\Database::connect();
$now = date('Y-m-d H:i:s');

// ---------------------------------------------------------------------------
// Cleanup: remove any previously-seeded beta data so the script is idempotent
// ---------------------------------------------------------------------------
$db->exec("SET session_replication_role = replica");
$cleanup = [
    "DELETE FROM stability_study_results WHERE timepoint_id IN (SELECT id FROM stability_study_timepoints WHERE study_id IN (SELECT id FROM stability_studies WHERE study_code LIKE 'BETA-%'))",
    "DELETE FROM stability_study_timepoints WHERE study_id IN (SELECT id FROM stability_studies WHERE study_code LIKE 'BETA-%')",
    "DELETE FROM stability_studies WHERE study_code LIKE 'BETA-%'",
    "DELETE FROM oos_investigations WHERE oos_id IN (SELECT id FROM oos_records WHERE oos_number LIKE 'OOS-%')",
    "DELETE FROM oos_records WHERE oos_number LIKE 'OOS-%'",
    "DELETE FROM result_revisions WHERE result_id IN (SELECT id FROM results WHERE sample_test_id IN (SELECT id FROM sample_tests WHERE sample_id IN (SELECT id FROM samples WHERE customer_id IN (SELECT id FROM customers WHERE customer_code LIKE 'BETA-%'))))",
    "DELETE FROM results WHERE sample_test_id IN (SELECT id FROM sample_tests WHERE sample_id IN (SELECT id FROM samples WHERE customer_id IN (SELECT id FROM customers WHERE customer_code LIKE 'BETA-%')))",
    "DELETE FROM sample_tests WHERE sample_id IN (SELECT id FROM samples WHERE customer_id IN (SELECT id FROM customers WHERE customer_code LIKE 'BETA-%'))",
    "DELETE FROM chain_of_custody WHERE sample_id IN (SELECT id FROM samples WHERE customer_id IN (SELECT id FROM customers WHERE customer_code LIKE 'BETA-%'))",
    "DELETE FROM deviation_actions WHERE deviation_id IN (SELECT id FROM deviations WHERE deviation_code LIKE 'DEV-%')",
    "DELETE FROM deviations WHERE deviation_code LIKE 'DEV-%'",
    "DELETE FROM capa_records WHERE capa_number LIKE 'CAPA-%'",
    "DELETE FROM eln_entry_attachments WHERE entry_id IN (SELECT id FROM eln_entries WHERE entry_code LIKE 'ENT-%')",
    "DELETE FROM eln_entries WHERE entry_code LIKE 'ENT-%'",
    "DELETE FROM eln_notebooks WHERE notebook_code LIKE 'NB-%'",
    "DELETE FROM environmental_alerts WHERE point_id IN (SELECT id FROM environmental_points WHERE point_name LIKE 'Beta%')",
    "DELETE FROM environmental_readings WHERE point_id IN (SELECT id FROM environmental_points WHERE point_name LIKE 'Beta%')",
    "DELETE FROM environmental_points WHERE point_name LIKE 'Beta%'",
    "DELETE FROM supplier_products WHERE supplier_id IN (SELECT id FROM suppliers WHERE supplier_code LIKE 'BETA-SUP-%')",
    "DELETE FROM supplier_qualifications WHERE supplier_id IN (SELECT id FROM suppliers WHERE supplier_code LIKE 'BETA-SUP-%')",
    "DELETE FROM suppliers WHERE supplier_code LIKE 'BETA-SUP-%'",
    "DELETE FROM training_assignments WHERE course_id IN (SELECT id FROM training_courses WHERE course_code LIKE 'BETA-TC-%')",
    "DELETE FROM training_courses WHERE course_code LIKE 'BETA-TC-%'",
    "DELETE FROM spc_readings WHERE batch_id IN (SELECT batch_number FROM batches WHERE batch_number LIKE 'BETA-B%')",
    "DELETE FROM qc_control_results WHERE control_lot_id IN (SELECT id FROM qc_control_lots WHERE lot_number LIKE 'BETA-QCL-%')",
    "DELETE FROM qc_control_lots WHERE lot_number LIKE 'BETA-QCL-%'",
    "DELETE FROM calibration_records WHERE certificate_number LIKE 'BETA-CAL-%'",
    "DELETE FROM calibration_schedules WHERE schedule_name LIKE 'BETA-%'",
    "DELETE FROM calibration_standards WHERE standard_code LIKE 'BETA-STD-%'",
    "DELETE FROM instrument_calibrations WHERE certificate_number LIKE 'BETA-ICAL-%'",
    "DELETE FROM instruments WHERE instrument_code LIKE 'BETA-INST-%'",
    "DELETE FROM chemical_inventory WHERE cas_number LIKE 'BETA-%'",
    "DELETE FROM plugins WHERE plugin_code LIKE 'beta-%'",
    "DELETE FROM manufacturers WHERE company_name LIKE '%Technologies' OR company_name IN ('Thermo Fisher Scientific','Mettler-Toledo','Shimadzu','Waters Corporation')",
    "DELETE FROM invoice_items WHERE invoice_id IN (SELECT id FROM invoices WHERE customer_id IN (SELECT id FROM customers WHERE customer_code LIKE 'BETA-%'))",
    "DELETE FROM payments WHERE invoice_id IN (SELECT id FROM invoices WHERE customer_id IN (SELECT id FROM customers WHERE customer_code LIKE 'BETA-%'))",
    "DELETE FROM invoices WHERE customer_id IN (SELECT id FROM customers WHERE customer_code LIKE 'BETA-%')",
    "DELETE FROM project_samples WHERE project_id IN (SELECT id FROM projects WHERE project_code LIKE 'BETA-PRJ-%')",
    "DELETE FROM projects WHERE project_code LIKE 'BETA-PRJ-%'",
    "DELETE FROM barcode_scan_logs WHERE barcode_value LIKE 'SMP-%'",
    "DELETE FROM privacy_logs WHERE description LIKE 'Beta privacy%'",
    "DELETE FROM consent_logs WHERE consent_type IN ('marketing','data_processing','third_party') AND user_agent = 'Beta-agent'",
    "DELETE FROM notifications WHERE title LIKE 'Beta%'",
    "DELETE FROM electronic_signatures WHERE signature_hash LIKE 'beta%'",
    "DELETE FROM sap_sync_logs WHERE error_message LIKE 'Beta SAP sync%' OR (sync_type LIKE 'Beta%')",
    "DELETE FROM translations WHERE module LIKE 'Beta%'",
    "DELETE FROM languages WHERE language_code IN ('fr','de','es','hi','zh')",
    "DELETE FROM samples WHERE customer_id IN (SELECT id FROM customers WHERE customer_code LIKE 'BETA-%')",
    "DELETE FROM batches WHERE batch_number LIKE 'BETA-B%'",
    "DELETE FROM customers WHERE customer_code LIKE 'BETA-%'",
];
foreach ($cleanup as $sql) {
    $db->exec($sql);
}
$db->exec("SET session_replication_role = DEFAULT");

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------
$NUM_CUSTOMERS = 100;          // beta customers
$SAMPLES_PER_CUSTOMER = 2;     // 2 samples each => 200 samples
$TESTS = range(1, 20);         // test ids
$NUM_BATCHES = 60;

// Deterministic PRNG for reproducible demo data
function rnd($min, $max) { return $min + mt_rand(0, mt_getrandmax()) / mt_getrandmax() * ($max - $min); }
function rndInt($min, $max) { return mt_rand($min, $max); }
function b($v) { return $v ? 't' : 'f'; }

$existingCustomers = (int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$existingSamples = (int)$db->query("SELECT COUNT(*) FROM samples")->fetchColumn();
echo "Before: customers={$existingCustomers}, samples={$existingSamples}\n";

// Analyst / reviewer / approver ids
$analysts = $db->query("SELECT id FROM users WHERE role_id = 2 ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
$reviewers = $db->query("SELECT id FROM users WHERE role_id = 3 ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
$approvers = $db->query("SELECT id FROM users WHERE role_id = 4 ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
$adminId = 1;
$sampleTypeIds = $db->query("SELECT id FROM sample_types ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
$productIds = $db->query("SELECT id FROM products ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
$instrumentIds = $db->query("SELECT id FROM instruments ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);

$firstNames = ['Acme', 'Vertex', 'Nova', 'Zenith', 'Quantum', 'Helix', 'Orbit', 'Summit', 'Prism', 'Titan', 'Apex', 'Cobalt', 'Delta', 'Echo', 'Fusion', 'Gravity', 'Horizon', 'Ionic', 'Junction', 'Kinetic'];
$lastNames = ['Pharma', 'BioTech', 'ChemLab', 'Analytix', 'LifeSciences', 'Industries', 'Materials', 'Diagnostics', 'Formulations', 'Polymers', 'Nutraceuticals', 'AgriScience', 'FoodTech', 'Coatings', 'SpecialtyChem', 'EnviroLabs', 'Metals', 'Cosmetics', 'PetroChem', 'Aerospace'];

$cityStates = [
    ['Austin','TX'], ['Boston','MA'], ['Seattle','WA'], ['Denver','CO'], ['Phoenix','AZ'],
    ['Chicago','IL'], ['Miami','FL'], ['Portland','OR'], ['Atlanta','GA'], ['Dallas','TX'],
    ['San Diego','CA'], ['Minneapolis','MN'], ['Nashville','TN'], ['Salt Lake City','UT'], ['Raleigh','NC'],
    ['Cleveland','OH'], ['Kansas City','MO'], ['Charlotte','NC'], ['Pittsburgh','PA'], ['Tampa','FL'],
];

echo "\n[1/8] Seeding customers (100)...\n";
$custStmt = $db->prepare("INSERT INTO customers (customer_code, customer_name, address, city, state, country, postal_code, contact_person, email, phone, is_active, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,TRUE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$custIds = [];
for ($i = 0; $i < $NUM_CUSTOMERS; $i++) {
    $name = $firstNames[$i % 20] . ' ' . $lastNames[($i * 7) % 20];
    $code = sprintf('BETA-%03d', $i + 1);
    [$city, $state] = $cityStates[$i % 20];
    $contact = 'Contact ' . ($i + 1);
    $email = strtolower(str_replace([' ', '.'], '', $name)) . $i . '@example.com';
    $custStmt->execute([
        $code, $name, "$i Beta Industrial Park", $city, $state, 'USA',
        sprintf('%05d', 10000 + $i), $contact, $email, '+1-555-' . sprintf('%04d', 1000 + $i),
    ]);
    $custIds[] = $db->lastInsertId();
}
echo "  Created " . count($custIds) . " customers\n";

echo "\n[2/8] Seeding batches (60)...\n";
$batchStmt = $db->prepare("INSERT INTO batches (batch_number, product_id, batch_size, manufacture_date, expiry_date, status, notes, created_by, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$batchIds = [];
for ($i = 0; $i < $NUM_BATCHES; $i++) {
    $productId = $productIds[$i % count($productIds)];
    $batchNumber = 'BETA-B' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
    $mfDate = date('Y-m-d', strtotime("-" . rndInt(10, 120) . " days"));
    $expDate = date('Y-m-d', strtotime($mfDate . " + " . rndInt(180, 730) . " days"));
    $status = ['Registered', 'In Progress', 'Reviewed', 'Approved', 'COA Released'][$i % 5];
    $batchStmt->execute([$batchNumber, $productId, rndInt(50, 2000) . ' kg', $mfDate, $expDate, $status, 'Beta batch for testing', $adminId]);
    $batchIds[] = $db->lastInsertId();
}
echo "  Created " . count($batchIds) . " batches\n";

echo "\n[3/8] Seeding samples (200)...\n";
$sampleStmt = $db->prepare("INSERT INTO samples
    (sample_code, customer_id, product_id, batch_number, batch_size, manufacture_date, expiry_date, received_date, target_completion_date, priority, status,
     assigned_analyst_id, assigned_reviewer_id, assigned_approver_id, registered_by, sample_type_id, sample_nature, sampling_date, sampled_by, sampling_point, notes, is_active, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,TRUE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$sampleIds = [];
$today = date('Y-m-d');
$datePrefix = date('Ymd');
$seq = 10000 + $existingSamples;
$statusPool = ['Registered', 'In Progress', 'Reviewed', 'Approved', 'COA Released'];
for ($c = 0; $c < $NUM_CUSTOMERS; $c++) {
    for ($k = 0; $k < $SAMPLES_PER_CUSTOMER; $k++) {
        $seq++;
        $code = 'SMP-' . $datePrefix . '-' . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
        $productId = $productIds[($c + $k) % count($productIds)];
        $batchId = $batchIds[($c * 2 + $k) % count($batchIds)];
        $batchNumber = $db->query("SELECT batch_number FROM batches WHERE id=" . (int)$batchId)->fetchColumn();
        $status = $statusPool[($c + $k) % count($statusPool)];
        $priority = ['Low', 'Normal', 'High', 'Urgent'][($c + $k) % 4];
        $received = date('Y-m-d', strtotime("-" . rndInt(1, 15) . " days"));
        $target = date('Y-m-d', strtotime($received . " + " . rndInt(5, 30) . " days"));
        $mfDate = date('Y-m-d', strtotime("-" . rndInt(20, 200) . " days"));
        $expDate = date('Y-m-d', strtotime($mfDate . " + " . rndInt(360, 1095) . " days"));
        $analyst = $analysts[array_rand($analysts)];
        $reviewer = $reviewers[array_rand($reviewers)];
        $approver = $approvers[array_rand($approvers)];
        $sampleType = $sampleTypeIds[($c + $k) % count($sampleTypeIds)];
        $sampleStmt->execute([
            $code, $custIds[$c], $productId, $batchNumber, rndInt(10, 500) . ' units',
            $mfDate, $expDate, $received, $target, $priority, $status,
            $analyst, $reviewer, $approver, $adminId, $sampleType,
            ['Raw Material', 'Finished Product', 'In-Process', 'Stability'][($c + $k) % 4],
            date('Y-m-d', strtotime("-" . rndInt(1, 10) . " days")),
            'Sampler ' . ($c + 1),
            'Point ' . (($c + $k) % 12 + 1),
            'Beta test sample',
        ]);
        $sampleIds[] = $db->lastInsertId();
    }
}
echo "  Created " . count($sampleIds) . " samples\n";

echo "\n[4/8] Seeding sample_tests and results...\n";
$stStmt = $db->prepare("INSERT INTO sample_tests (sample_id, test_id, assigned_to, status, assigned_at, completed_at, reviewed_at, approved_at, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$resStmt = $db->prepare("INSERT INTO results
    (sample_test_id, result_value, result_text, is_within_spec, entered_by, entered_at, reviewed_by, reviewed_at, approved_by, approved_at, remarks, revision, created_at, updated_at,
     uncertainty, k_factor, confidence_interval, instrument_id, replicate_count)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,?,?,?,?,?)");

$stCount = 0; $resCount = 0; $oosCount = 0;
$testInfo = [];
$trows = $db->query("SELECT id, min_spec_limit, max_spec_limit FROM tests ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
foreach ($trows as $t) { $testInfo[$t['id']] = [$t['min_spec_limit'], $t['max_spec_limit']]; }

$oosStmt = $db->prepare("INSERT INTO oos_records
    (oos_number, sample_id, sample_test_id, result_id, test_parameter, specification_range, result_value, unit, description, severity, status, initiated_by, assigned_to, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");

foreach ($sampleIds as $si => $sampleId) {
    // 1-3 tests per sample
    $numTests = rndInt(1, 3);
    $chosen = array_rand($TESTS, $numTests);
    if (!is_array($chosen)) $chosen = [$chosen];
    foreach ($chosen as $tidx) {
        $testId = $TESTS[$tidx];
        $stCount++;
        $status = ['Pending', 'In Progress', 'Completed', 'Reviewed', 'Approved'][$stCount % 5];
        $analyst = $analysts[array_rand($analysts)];
        $reviewer = $reviewers[array_rand($reviewers)];
        $approver = $approvers[array_rand($approvers)];
        $completed = $status !== 'Pending' && $status !== 'In Progress' ? $now : null;
        $reviewedAt = ($status === 'Reviewed' || $status === 'Approved') ? $now : null;
        $approvedAt = $status === 'Approved' ? $now : null;
        $stStmt->execute([$sampleId, $testId, $analyst, $status, $now, $completed, $reviewedAt, $approvedAt]);
        $stId = $db->lastInsertId();

        // Record a result if there's a test in progress or later
        if ($status !== 'Pending') {
            $resCount++;
            [$min, $max] = $testInfo[$testId];
            // value near midpoint; occasionally out of spec
            $within = true;
            $value = null;
            if ($min === null && $max === null) {
                $value = round(rnd(1, 100), 2);
                $within = true;
            } else {
                $mid = ($min + $max) / 2;
                $span = ($max - $min) / 2;
                $roll = rnd(0, 100);
                if ($roll > 95) { // 5% OOS
                    $value = round($max + rnd(0.5, 3), 2);
                    $within = false;
                } else {
                    $value = round($mid + rnd(-$span * 0.6, $span * 0.6), 2);
                    $within = true;
                }
            }
            $instrumentId = $instrumentIds ? $instrumentIds[array_rand($instrumentIds)] : null;
            $resStmt->execute([
                $stId, $value, null, b($within), $analyst, $now,
                $status === 'Reviewed' || $status === 'Approved' ? $reviewer : null,
                $status === 'Reviewed' || $status === 'Approved' ? $now : null,
                $status === 'Approved' ? $approver : null,
                $status === 'Approved' ? $now : null,
                'Beta result', round(rnd(0.1, 2.5), 2), 2.0, '95%', $instrumentId, rndInt(1, 3),
            ]);
            $resId = $db->lastInsertId();

            // Auto-create OOS for out-of-spec results
            if (!$within) {
                $oosCount++;
                $oosNumber = 'OOS-' . date('Ymd') . '-' . str_pad((string)$oosCount, 4, '0', STR_PAD_LEFT);
                $oosStmt->execute([
                    $oosNumber, $sampleId, $stId, $resId,
                    'Test ' . $testId, ($min ?? 0) . ' - ' . ($max ?? 'N/A'),
                    $value, null, 'Out of specification during beta seeding',
                    ['Minor', 'Major', 'Critical'][$oosCount % 3], 'Open', $analyst, $analyst,
                ]);
            }
        }
    }
}
echo "  Created {$stCount} sample_tests, {$resCount} results, {$oosCount} OOS records\n";

echo "\n[5/8] Seeding module data (quality, ELN, env, suppliers, training)...\n";

// --- Deviations ---
$devStmt = $db->prepare("INSERT INTO deviations (deviation_code, title, description, deviation_type, severity, source, reported_by, reported_date, status, impact_assessment, root_cause, corrective_action, preventive_action, product_id, sample_id, created_by, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 15; $i++) {
    $devStmt->execute([
        'DEV-' . date('Ymd') . '-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
        'Beta deviation ' . ($i + 1),
        'Detected during beta test of module',
        ['Process', 'Equipment', 'Testing', 'Documentation', 'Environmental'][$i % 5],
        ['Minor', 'Major', 'Critical'][$i % 3],
        'Samples', $analysts[$i % count($analysts)], date('Y-m-d', strtotime("-" . $i . " days")),
        ['Open', 'Under Investigation', 'Closed'][$i % 3],
        'Impact assessment placeholder', 'Root cause placeholder',
        'Corrective action placeholder', 'Preventive action placeholder',
        $productIds[$i % count($productIds)], $sampleIds[$i % count($sampleIds)], $adminId,
    ]);
}

// --- CAPA ---
$capaStmt = $db->prepare("INSERT INTO capa_records (capa_number, title, description, source_type, source_reference_id, source_reference_type, root_cause, corrective_action_plan, preventive_action_plan, priority, status, due_date, assigned_to, created_by, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 10; $i++) {
    $capaStmt->execute([
        'CAPA-' . date('Ymd') . '-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
        'Beta CAPA ' . ($i + 1),
        'CAPA generated from beta findings',
        ['Deviation', 'OOS', 'Audit', 'Customer Complaint'][$i % 4], $i + 1, 'deviation',
        'Root cause placeholder',
        'Corrective plan placeholder', 'Preventive plan placeholder',
        ['Low', 'Medium', 'High', 'Critical'][$i % 4],
        ['Open', 'In Progress', 'Closed'][$i % 3],
        date('Y-m-d', strtotime("+" . rndInt(7, 60) . " days")),
        $reviewers[$i % count($reviewers)], $adminId,
    ]);
}

// --- ELN ---
$nbStmt = $db->prepare("INSERT INTO eln_notebooks (notebook_code, notebook_name, description, category, owner_id, is_active, created_at, updated_at)
    VALUES (?,?,?,?,?,TRUE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$notebookIds = [];
for ($i = 0; $i < 6; $i++) {
    $nbStmt->execute(['NB-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT), 'Beta Notebook ' . ($i + 1), 'ELN for beta testing', ['R&D', 'QC', 'Process', 'Analytical'][$i % 4], $analysts[$i % count($analysts)]]);
    $notebookIds[] = $db->lastInsertId();
}
$entryStmt = $db->prepare("INSERT INTO eln_entries (entry_code, notebook_id, title, content, entry_type, status, created_by, tags, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 30; $i++) {
    $entryStmt->execute([
        'ENT-' . date('Ymd') . '-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
        $notebookIds[$i % count($notebookIds)],
        'Beta entry ' . ($i + 1),
        'Analysis notes recorded during beta test. Observation #' . ($i + 1) . '.',
        ['General', 'Experiment', 'Observation', 'Result'][$i % 4],
        ['Draft', 'Final', 'Reviewed'][$i % 3],
        $analysts[$i % count($analysts)], 'beta,' . ($i % 5 + 1),
    ]);
}

// --- Environmental points / readings / alerts ---
$epStmt = $db->prepare("INSERT INTO environmental_points (point_name, location_name, monitoring_type, unit, min_threshold, max_threshold, is_active, created_at, updated_at)
    VALUES (?,?,?,?,?,?,TRUE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$envPointIds = [];
for ($i = 0; $i < 12; $i++) {
    $monitor = ['Temperature', 'Humidity', 'Pressure', 'Particulate Count'][$i % 4];
    $unit = $monitor === 'Temperature' ? '°C' : ($monitor === 'Humidity' ? '%RH' : ($monitor === 'Pressure' ? 'Pa' : 'count/m³'));
    $min = $monitor === 'Temperature' ? 2 : 20;
    $max = $monitor === 'Temperature' ? 8 : 80;
    $epStmt->execute(['Beta Point ' . ($i + 1), 'Lab Room ' . ($i % 4 + 1), $monitor, $unit, $min, $max]);
    $envPointIds[] = $db->lastInsertId();
}
$erStmt = $db->prepare("INSERT INTO environmental_readings (point_id, reading_value, unit, recorded_by, notes, created_at) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)");
$alertStmt = $db->prepare("INSERT INTO environmental_alerts (point_id, alert_type, reading_value, threshold_value, message, is_resolved, created_at) VALUES (?,?,?,?,?,FALSE,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 60; $i++) {
    $pointId = $envPointIds[$i % count($envPointIds)];
    $monitor = ['Temperature', 'Humidity', 'Pressure', 'Particulate Count'][$pointId % 4];
    $unit = $monitor === 'Temperature' ? '°C' : ($monitor === 'Humidity' ? '%RH' : ($monitor === 'Pressure' ? 'Pa' : 'count/m³'));
    $val = round(rnd(1, 10), 1);
    $erStmt->execute([$pointId, $val, $unit, $analysts[$i % count($analysts)], 'Beta reading']);
    if ($i % 10 === 0) {
        $alertStmt->execute([$pointId, 'Threshold Exceeded', $val, 8, 'Beta alert for point ' . $pointId]);
    }
}

// --- Suppliers + qualifications ---
$suppStmt = $db->prepare("INSERT INTO suppliers (supplier_code, supplier_name, supplier_type, address, city, state, country, postal_code, contact_person, email, phone, website, tax_id, payment_terms, rating, status, is_approved, notes, created_by, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,TRUE,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$supplierIds = [];
for ($i = 0; $i < 12; $i++) {
    $suppStmt->execute([
        'BETA-SUP-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
        'Beta Supplier ' . ($i + 1),
        ['Raw Material', 'Packaging', 'Services', 'Consumables'][$i % 4],
        $i . ' Supplier Ave', 'City ' . ($i + 1), 'ST', 'USA', sprintf('%05d', 20000 + $i),
        'Supplier Contact ' . ($i + 1), 'supplier' . ($i + 1) . '@example.com', '+1-555-20' . sprintf('%03d', $i),
        'https://supplier' . ($i + 1) . '.example.com', 'TAX-' . $i, 'Net 30', ($i % 5) + 1,
        ['Active', 'Inactive'][$i % 2], 'Beta supplier', $adminId,
    ]);
    $supplierIds[] = $db->lastInsertId();
}
$sqStmt = $db->prepare("INSERT INTO supplier_qualifications (supplier_id, qualification_type, qualification_date, expiry_date, result, certificate_number, audited_by, notes, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 12; $i++) {
    $sqStmt->execute([
        $supplierIds[$i], ['Audit', 'Questionnaire', 'Certification'][$i % 3],
        date('Y-m-d', strtotime("-" . rndInt(10, 100) . " days")),
        date('Y-m-d', strtotime("+" . rndInt(100, 400) . " days")),
        ['Qualified', 'Conditional', 'Approved'][$i % 3], 'CERT-' . ($i + 1), $reviewers[$i % count($reviewers)], 'Beta qualification',
    ]);
}

// --- Training ---
$tcStmt = $db->prepare("INSERT INTO training_courses (course_code, course_name, description, course_type, duration_hours, provider, requires_certification, validity_days, is_active, created_by, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,TRUE,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$courseIds = [];
for ($i = 0; $i < 10; $i++) {
    $tcStmt->execute([
        'BETA-TC-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
        'Beta Course ' . ($i + 1),
        'Training course for beta users',
        ['SOP', 'Safety', 'Quality', 'Instrument'][$i % 4],
        rnd(1, 8), 'PlexiQ Training', b($i % 2 === 0), rndInt(180, 730), $adminId,
    ]);
    $courseIds[] = $db->lastInsertId();
}
$taStmt = $db->prepare("INSERT INTO training_assignments (course_id, user_id, assigned_by, due_date, status, score, completed_date, certificate_number, notes, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 40; $i++) {
    $courseId = $courseIds[$i % count($courseIds)];
    $userId = $analysts[$i % count($analysts)];
    if ($db->query("SELECT 1 FROM training_assignments WHERE course_id = {$courseId} AND user_id = {$userId}")->fetchColumn()) {
        continue;
    }
    $taStmt->execute([
        $courseId, $userId, $adminId,
        date('Y-m-d', strtotime("+" . rndInt(5, 90) . " days")),
        ['Pending', 'In Progress', 'Completed'][$i % 3],
        $i % 3 === 2 ? round(rnd(70, 100), 1) : null,
        $i % 3 === 2 ? date('Y-m-d', strtotime("-" . rndInt(1, 30) . " days")) : null,
        $i % 3 === 2 ? 'CERT-' . $i : null, 'Beta assignment',
    ]);
}

echo "  Deviations=15, CAPA=10, ELN=6+30, Env=12+60+6, Suppliers=12+12, Training=10+40\n";

echo "\n[6/8] Seeding SPC, QC, stability, calibration, inventory...\n";

// --- SPC readings (add to existing 600) ---
$spcParamIds = $db->query("SELECT id, spec_target FROM spc_parameters ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
$spcStmt = $db->prepare("INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, entered_by, notes, created_at)
    VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)");
$spcBatchIds = $db->query("SELECT batch_number FROM batches LIMIT 10")->fetchAll(\PDO::FETCH_COLUMN);
foreach ($spcParamIds as $sp) {
    $target = (float)($sp['spec_target'] ?? 5);
    for ($i = 0; $i < 30; $i++) {
        $spcStmt->execute([
            $sp['id'], $spcBatchIds[$i % count($spcBatchIds)],
            date('Y-m-d H:i:s', strtotime("-" . ($i * 3) . " days")),
            round($target + rnd(-0.5, 0.5) * $target * 0.05, 4),
            $analysts[$i % count($analysts)], 'Beta SPC reading',
        ]);
    }
}

// --- QC control lots + results ---
$qcLotStmt = $db->prepare("INSERT INTO qc_control_lots (lot_number, description, manufacturer, material_type, target_mean, target_sd, unit, expiry_date, is_active, created_by, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,TRUE,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$qcLotIds = [];
for ($i = 0; $i < 8; $i++) {
    $mean = round(rnd(50, 100), 2);
    $sd = round(rnd(0.5, 2.5), 2);
    $qcLotStmt->execute([
        'BETA-QCL-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
        'Beta control lot ' . ($i + 1),
        'PlexiQ Controls', ['Assay', 'pH', 'Moisture', 'Viscosity'][$i % 4],
        $mean, $sd, ['mg', '%', 'pH', 'cP'][$i % 4],
        date('Y-m-d', strtotime("+" . rndInt(200, 700) . " days")), $adminId,
    ]);
    $qcLotIds[] = $db->lastInsertId();
}
$qcResStmt = $db->prepare("INSERT INTO qc_control_results (control_lot_id, parameter_id, test_id, instrument_id, result_value, entered_by, entered_at, notes)
    VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP,?)");
foreach ($qcLotIds as $qi => $lotId) {
    $mean = (float)$db->query("SELECT target_mean FROM qc_control_lots WHERE id=" . (int)$lotId)->fetchColumn();
    for ($i = 0; $i < 20; $i++) {
        $qcResStmt->execute([
            $lotId, $spcParamIds[$qi % count($spcParamIds)]['id'],
            $TESTS[$qi % count($TESTS)], $instrumentIds ? $instrumentIds[0] : null,
            round($mean + rnd(-2, 2), 2), $analysts[$i % count($analysts)], 'Beta QC result',
        ]);
    }
}

// --- Stability studies + timepoints + results ---
$stabStmt = $db->prepare("INSERT INTO stability_studies (study_code, study_name, product_id, batch_id, study_type, condition_temperature, condition_humidity, condition_light, storage_condition, protocol_ref, status, start_date, end_date, created_by, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$stabIds = [];
for ($i = 0; $i < 10; $i++) {
    $stabStmt->execute([
        'BETA-STB-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
        'Beta Stability Study ' . ($i + 1),
        $productIds[$i % count($productIds)], $batchIds[$i % count($batchIds)],
        ['Long Term', 'Accelerated', 'Intermediate'][$i % 3],
        $i % 3 === 1 ? 40 : 25, $i % 3 === 1 ? 75 : 60, 'Dark',
        'ICH conditions', 'PROTOCOL-001',
        ['Active', 'Completed', 'On Hold'][$i % 3],
        date('Y-m-d', strtotime("-" . rndInt(30, 200) . " days")),
        date('Y-m-d', strtotime("+" . rndInt(200, 800) . " days")),
        $adminId,
    ]);
    $stabIds[] = $db->lastInsertId();
}
$tpStmt = $db->prepare("INSERT INTO stability_study_timepoints (study_id, timepoint_label, day_offset, scheduled_date, completed_date, status, notes, sort_order, created_at)
    VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
$stabResStmt = $db->prepare("INSERT INTO stability_study_results (timepoint_id, test_id, result_value, specification_limit, result_status, tested_by, tested_at, created_at)
    VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
foreach ($stabIds as $sid) {
    foreach ([0, 30, 90, 180, 365] as $tp => $day) {
        $label = $day === 0 ? 'Initial' : ($day . ' day');
        $completed = $tp <= 2 ? date('Y-m-d', strtotime("+" . $day . " days")) : null;
        $status = $tp <= 2 ? 'Completed' : 'Pending';
        $tpStmt->execute([$sid, $label, $day, date('Y-m-d', strtotime("+" . $day . " days")), $completed, $status, null, $tp]);
        $tpId = $db->lastInsertId();
        if ($status === 'Completed') {
            $stabResStmt->execute([$tpId, $TESTS[$tp % count($TESTS)], round(rnd(90, 100), 2), '90-100', 'Pass', $analysts[$tp % count($analysts)], $now]);
        }
    }
}

// --- Instruments (add more) ---
$instStmt = $db->prepare("INSERT INTO instruments (instrument_code, instrument_name, model, manufacturer, interface_type, is_active, created_at, updated_at)
    VALUES (?,?,?,?,?,TRUE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$instNames = [
    ['BETA-INST-HPLC2', 'HPLC System 2', '1260 Infinity II', 'Agilent', 'CSV'],
    ['BETA-INST-GC1', 'GC System 1', '7890B', 'Agilent', 'CSV'],
    ['BETA-INST-PH1', 'pH Meter 1', 'SevenCompact', 'Mettler', 'TEXT'],
    ['BETA-INST-UV1', 'UV-Vis Spec', 'Cary 60', 'Agilent', 'CSV'],
    ['BETA-INST-BAL1', 'Analytical Balance', 'XS205', 'Mettler', 'TEXT'],
    ['BETA-INST-KARL1', 'Karl Fischer Titrator', 'V20S', 'Mettler', 'CSV'],
    ['BETA-INST-FTIR1', 'FTIR Spectrometer', 'Nicolet iS5', 'Thermo', 'CSV'],
    ['BETA-INST-VIS1', 'Viscometer', 'DV2T', 'Brookfield', 'TEXT'],
];
foreach ($instNames as $ix => $in) {
    $instStmt->execute($in);
    $newInstId = $db->lastInsertId();

    // Instrument calibrations (legacy table)
    $db->prepare("INSERT INTO instrument_calibrations (instrument_id, calibration_date, calibrated_by, calibration_standard, result, certificate_number, next_calibration_date, notes, created_by, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
        ->execute([$newInstId, date('Y-m-d', strtotime("-30 days")), 'Beta Calibrator', 'NIST Traceable', 'Pass', 'BETA-ICAL-' . $ix, date('Y-m-d', strtotime("+330 days")), 'Beta calibration', $adminId]);

    // Calibration standards + schedules (enhanced module)
    $stdId = null;
    if ($ix < 4) {
        $db->prepare("INSERT INTO calibration_standards (standard_code, standard_name, standard_type, serial_number, certificate_number, calibration_interval_days, last_calibration_date, next_calibration_date, supplier_id, status, location, notes, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
            ->execute([
                'BETA-STD-' . ($ix + 1), 'Cal Standard ' . ($ix + 1), ['Reference', 'Working'][$ix % 2], 'SER-' . ($ix + 1), 'BETA-CAL-' . ($ix + 1), 365,
                date('Y-m-d', strtotime("-30 days")), date('Y-m-d', strtotime("+335 days")), $supplierIds[$ix % count($supplierIds)],
                'Active', 'Lab Bench ' . ($ix + 1), 'Beta standard',
            ]);
        $stdId = $db->lastInsertId();
        $db->prepare("INSERT INTO calibration_schedules (instrument_id, standard_id, schedule_name, frequency_days, last_due_date, next_due_date, assigned_to, is_active, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,TRUE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
            ->execute([$newInstId, $stdId, 'Quarterly Cal ' . ($ix + 1), 90, date('Y-m-d', strtotime("-30 days")), date('Y-m-d', strtotime("+60 days")), $analysts[$ix % count($analysts)]]);
        $db->prepare("INSERT INTO calibration_records (instrument_id, standard_id, calibration_date, calibrated_by, calibration_type, result, as_found_value, as_left_value, uncertainty, certificate_number, due_date, status, notes, performed_by, next_calibration_date, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
            ->execute([$newInstId, $stdId, date('Y-m-d', strtotime("-30 days")), $analysts[$ix % count($analysts)], 'Quarterly', 'Pass', 'In tolerance', 'In tolerance', 0.1, 'BETA-CAL-' . ($ix + 1), date('Y-m-d', strtotime("+60 days")), 'Completed', 'Beta record', $analysts[$ix % count($analysts)], date('Y-m-d', strtotime("+60 days"))]);
    }
}

// --- Chemical inventory ---
$chemStmt = $db->prepare("INSERT INTO chemical_inventory (chemical_name, cas_number, catalog_number, supplier, unit_type, quantity, minimum_quantity, unit_price, storage_location, hazard_symbols, safety_data_sheet, expiry_date, received_date, opened_date, status, created_by, is_active, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,TRUE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
// --- Manufacturers + plugins (so detail pages render) ---
$mfrStmt = $db->prepare("INSERT INTO manufacturers (company_name, address, city, state, country, postal_code, phone, email, website, logo_path, is_active, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,TRUE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
foreach ([['Agilent Technologies','5301 Stevens Creek Blvd','Santa Clara','CA','USA','95051','+1-408-345-8886','info@agilent.com','https://www.agilent.com',null],
          ['Thermo Fisher Scientific','168 Third Avenue','Waltham','MA','USA','02451','+1-781-622-1000','info@thermofisher.com','https://www.thermofisher.com',null],
          ['Mettler-Toledo','1900 Polaris Parkway','Columbus','OH','USA','43240','+1-614-438-4511','info@mt.com','https://www.mt.com',null],
          ['Shimadzu','7102 Riverwood Drive','Columbia','MD','USA','21046','+1-410-381-1227','info@shimadzu.com','https://www.shimadzu.com',null],
          ['Waters Corporation','34 Maple Street','Milford','MA','USA','01757','+1-508-478-2000','info@waters.com','https://www.waters.com',null]] as $m) {
    $mfrStmt->execute($m);
}
$db->prepare("INSERT INTO plugins (plugin_code, plugin_name, description, version, author, entry_point, settings, is_active, is_system, installed_at, updated_at)
    VALUES (?,?,?,?,?,?,?,TRUE,FALSE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
    ->execute(['beta-monitor', 'Beta Monitoring', 'Demo plugin for beta validation', '1.0.0', 'PlexiQ', 'plugins/beta-monitor', json_encode(['enabled' => true, 'interval' => 'hourly'])]);
$chems = [
    ['Methanol', '67-56-1', 'M-1000', 'Fisher', 'L', 50, 10, 15.5, 'Flammable Cabinet', 'F, T', 'SDS-ME001', 500, 2, 1, 'In Stock'],
    ['Acetonitrile', '75-05-8', 'A-2000', 'Sigma', 'L', 80, 20, 22.0, 'Flammable Cabinet', 'F', 'SDS-AC002', 450, 2, 1, 'In Stock'],
    ['Sodium Hydroxide', '1310-73-2', 'S-3000', 'VWR', 'kg', 15, 5, 8.0, 'Corrosives', 'C', 'SDS-NH003', 700, 2, 1, 'In Stock'],
    ['Hydrochloric Acid', '7647-01-0', 'H-4000', 'Fisher', 'L', 30, 10, 9.5, 'Corrosives', 'C', 'SDS-HC004', 800, 2, 1, 'In Stock'],
    ['Ethanol', '64-17-5', 'E-5000', 'Sigma', 'L', 40, 10, 12.0, 'Flammable Cabinet', 'F', 'SDS-ET005', 600, 2, 1, 'In Stock'],
    ['Water HPLC Grade', '7732-18-5', 'W-6000', 'Millipore', 'L', 100, 25, 6.0, 'General', null, 'SDS-WA006', 400, 2, 1, 'In Stock'],
    ['Formic Acid', '64-18-6', 'F-7000', 'Sigma', 'mL', 200, 50, 3.0, 'Corrosives', 'C', 'SDS-FA007', 500, 2, 1, 'In Stock'],
    ['Ammonium Acetate', '631-61-8', 'A-8000', 'VWR', 'kg', 8, 3, 18.0, 'General', null, 'SDS-AA008', 700, 2, 1, 'In Stock'],
    ['Toluene', '108-88-3', 'T-9000', 'Fisher', 'L', 25, 8, 10.0, 'Flammable Cabinet', 'F, Xn', 'SDS-TL009', 500, 2, 1, 'In Stock'],
    ['Acetone', '67-64-1', 'A-11000', 'Sigma', 'L', 35, 10, 8.5, 'Flammable Cabinet', 'F', 'SDS-AC010', 550, 2, 1, 'In Stock'],
];
foreach ($chems as $idx => $c) {
    $c[11] = date('Y-m-d', strtotime("+" . $c[11] . " days"));
    $c[12] = date('Y-m-d', strtotime("-" . $c[12] . " days"));
    $c[13] = date('Y-m-d', strtotime("-" . $c[13] . " days"));
    $chemStmt->execute(array_merge($c, [$adminId]));
}

// --- Chain of custody ---
$cocStmt = $db->prepare("INSERT INTO chain_of_custody (sample_id, transfer_from, transfer_to, transferred_by, received_by, transferred_at, received_at, location, condition_note, sealed, seal_number, custody_reason, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 40; $i++) {
    $sid = $sampleIds[$i % count($sampleIds)];
    $cocStmt->execute([
        $sid, 'Receiving', 'QC Laboratory', $adminId, $analysts[$i % count($analysts)],
        date('Y-m-d H:i:s', strtotime("-" . rndInt(1, 10) . " days")),
        $i % 3 === 0 ? date('Y-m-d H:i:s', strtotime("-" . rndInt(0, 5) . " days")) : null,
        'QC Lab Bench ' . ($i % 5 + 1), 'Sample intact', b($i % 2 === 0), 'SEAL-' . ($i + 1), 'Testing handoff',
    ]);
}

// --- Notifications ---
$notifStmt = $db->prepare("INSERT INTO notifications (user_id, notification_type, title, message, link, is_read, sent_at, read_at)
    VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP,?)");
for ($i = 0; $i < 40; $i++) {
    $notifStmt->execute([
        $analysts[$i % count($analysts)], 'Sample', 'New sample assigned', 'Sample SMP-XXXX assigned for testing', '/tests/pending',
        $i % 2 === 0 ? b(true) : b(false), $i % 2 === 0 ? $now : null,
    ]);
}

echo "  SPC += " . (count($spcParamIds) * 30) . ", QC=8 lots+160, Stability=10+50+tp, Instruments=8, ChemInv=10, CoC=40, Notifications=40\n";

echo "\n[7/8] Seeding billing, projects, API, barcode, compliance...\n";

// --- Billing: invoices + items + payments ---
$invStmt = $db->prepare("INSERT INTO invoices (invoice_number, customer_id, sample_id, invoice_date, due_date, subtotal, tax_amount, discount_amount, total_amount, status, notes, created_by, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$invItemStmt = $db->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total_price, sort_order, created_at) VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)");
$invoiceIds = [];
for ($i = 0; $i < 40; $i++) {
    $sub = round(rnd(200, 5000), 2);
    $tax = round($sub * 0.1, 2);
    $total = round($sub + $tax, 2);
    $invStmt->execute([
        'INV-' . date('Ymd') . '-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
        $custIds[$i % count($custIds)], $sampleIds[$i % count($sampleIds)],
        date('Y-m-d', strtotime("-" . rndInt(1, 30) . " days")),
        date('Y-m-d', strtotime("+" . rndInt(10, 60) . " days")),
        $sub, $tax, 0, $total,
        ['Draft', 'Sent', 'Paid', 'Overdue'][$i % 4], 'Beta invoice', $adminId,
    ]);
    $invoiceIds[] = $db->lastInsertId();
    for ($j = 0; $j < rndInt(1, 4); $j++) {
        $invItemStmt->execute([$invoiceIds[$i], 'Testing service item ' . ($j + 1), rndInt(1, 10), round(rnd(20, 200), 2), round(rnd(20, 800), 2), $j]);
    }
}
$payStmt = $db->prepare("INSERT INTO payments (invoice_id, amount, payment_method, payment_date, reference_number, notes, created_by, created_at) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
$payMethods = ['Credit Card', 'Bank Transfer', 'Cheque'];
for ($i = 0; $i < 15; $i++) {
    $payStmt->execute([$invoiceIds[$i * 2 % count($invoiceIds)], round(rnd(100, 1000), 2), $payMethods[$i % 3], date('Y-m-d', strtotime("-" . rndInt(1, 20) . " days")), 'TXN-' . $i, 'Beta payment', $adminId]);
}

// --- Projects ---
$projStmt = $db->prepare("INSERT INTO projects (project_code, project_name, description, status, priority, start_date, target_end_date, manager_id, created_by, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$projectIds = [];
for ($i = 0; $i < 10; $i++) {
    $projStmt->execute([
        'BETA-PRJ-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
        'Beta Project ' . ($i + 1),
        'Project for beta validation',
        ['Active', 'Completed', 'On Hold', 'Cancelled'][$i % 4],
        ['Low', 'Medium', 'High', 'Critical'][$i % 4],
        date('Y-m-d', strtotime("-" . rndInt(5, 90) . " days")),
        date('Y-m-d', strtotime("+" . rndInt(20, 120) . " days")),
        $reviewers[$i % count($reviewers)], $adminId,
    ]);
    $projectIds[] = $db->lastInsertId();
}
$psStmt = $db->prepare("INSERT INTO project_samples (project_id, sample_id, notes, created_at) VALUES (?,?,?,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 30; $i++) {
    $psStmt->execute([$projectIds[$i % count($projectIds)], $sampleIds[$i % count($sampleIds)], 'Beta link']);
}

// --- Barcode scan logs ---
$barcodeStmt = $db->prepare("INSERT INTO barcode_scan_logs (barcode_value, entity_type, entity_id, scanner_id, location, scanned_by, scanned_at) VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 50; $i++) {
    $barcodeStmt->execute([
        'SMP-' . str_pad((string)$sampleIds[$i % count($sampleIds)], 5, '0', STR_PAD_LEFT),
        'sample', $sampleIds[$i % count($sampleIds)], 'Scanner-01', 'QC Lab', $analysts[$i % count($analysts)],
    ]);
}

// --- Compliance logs (privacy + consent) ---
$plStmt = $db->prepare("INSERT INTO privacy_logs (user_id, action_type, description, ip_address, created_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 30; $i++) {
    $plStmt->execute([$analysts[$i % count($analysts)], ['data_view', 'data_export', 'data_correct', 'data_delete'][$i % 4], 'Beta privacy action', '127.0.0.1']);
}
$clStmt = $db->prepare("INSERT INTO consent_logs (user_id, consent_type, consent_granted, ip_address, user_agent, created_at) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 30; $i++) {
    $clStmt->execute([$analysts[$i % count($analysts)], ['marketing', 'data_processing', 'third_party'][$i % 3], b($i % 2 === 0), '127.0.0.1', 'Beta-agent']);
}

// --- Languages + translations ---
foreach (['fr' => 'French', 'de' => 'German', 'es' => 'Spanish', 'hi' => 'Hindi', 'zh' => 'Chinese'] as $lc => $ln) {
    $db->prepare("INSERT INTO languages (language_code, language_name, is_rtl, is_default, is_active, created_at) VALUES (?,?,FALSE,FALSE,TRUE,CURRENT_TIMESTAMP)")
        ->execute([$lc, $ln]);
}
$langIds = $db->query("SELECT id FROM languages ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
$transStmt = $db->prepare("INSERT INTO translations (language_id, translation_key, translation_value, module, created_at, updated_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON CONFLICT (language_id, translation_key) DO NOTHING");
$keys = ['dashboard.title' => 'Dashboard', 'samples.title' => 'Samples', 'tests.title' => 'Tests', 'customers.title' => 'Customers', 'results.title' => 'Results', 'coa.title' => 'Certificate of Analysis'];
foreach ($langIds as $lid) {
    foreach ($keys as $k => $v) {
        $transStmt->execute([$lid, $k, '[L' . $lid . '] ' . $v, 'global']);
    }
}

// --- SAP sync logs ---
$sapStmt = $db->prepare("INSERT INTO sap_sync_logs (sync_type, entity_type, entity_id, status, error_message, retry_count, max_retries, synced_at, created_at) VALUES (?,?,?,?,?,0,3,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 30; $i++) {
    $sapStmt->execute([
        ['Push', 'Pull'][$i % 2],
        ['sample', 'customer', 'product', 'test'][$i % 4], $i + 1,
        ['Success', 'Pending', 'Failed'][$i % 3],
        $i % 3 === 2 ? 'Beta SAP sync failure' : null,
    ]);
}

// --- E-signature audit ---
$esignStmt = $db->prepare("INSERT INTO electronic_signatures (user_id, action_type, entity_type, entity_id, signature_hash, ip_address, created_at) VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)");
for ($i = 0; $i < 20; $i++) {
    $esignStmt->execute([$approvers[$i % count($approvers)], 'approval', 'sample_test', $i + 1, hash('sha256', 'beta' . $i), '127.0.0.1']);
}

echo "  Billing=40+items+15 payments, Projects=10+30, Barcode=50, Privacy=30, Consent=30, Langs=5, SAP=30, ESign=20\n";

echo "\n[8/8] Updating sample workflow timestamps for status consistency...\n";
// Update COA released samples with reviewed/approved timestamps
$db->exec("UPDATE samples s SET
    reviewed_at = COALESCE(reviewed_at, created_at),
    approved_at = COALESCE(approved_at, created_at),
    coa_released_at = COALESCE(coa_released_at, created_at),
    coa_released_by = COALESCE(coa_released_by, 1)
    WHERE status = 'COA Released'");

// Report
$counts = [];
$tables = ['customers','samples','batches','sample_tests','results','deviations','oos_records','capa_records','eln_entries','eln_notebooks','environmental_points','environmental_readings','environmental_alerts','suppliers','supplier_qualifications','training_courses','training_assignments','spc_readings','qc_control_lots','qc_control_results','stability_studies','stability_study_timepoints','stability_study_results','instruments','instrument_calibrations','calibration_standards','calibration_records','calibration_schedules','chemical_inventory','chain_of_custody','notifications','invoices','invoice_items','payments','projects','project_samples','barcode_scan_logs','privacy_logs','consent_logs','languages','translations','sap_sync_logs','electronic_signatures'];
foreach ($tables as $t) {
    try { $counts[$t] = (int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn(); } catch (\Throwable $e) { $counts[$t] = 'ERR'; }
}
echo "\n===== FINAL COUNTS =====\n";
foreach ($counts as $t => $c) echo str_pad($t, 28) . " => {$c}\n";
echo "\nSeeding complete.\n";
