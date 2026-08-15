<?php
/**
 * PlexiQ LIMS - Beta Smoke Test
 * Logs in as admin and walks every module page, reporting non-200 responses.
 * Run from project root: php beta/smoke_test.php
 */

require __DIR__ . '/../vendor/autoload.php';

$base = 'http://localhost:8080';

// --- Env loading (mimic public/index.php) ---
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}
require_once __DIR__ . '/../app/Helpers/helpers.php';

$db = \App\Helpers\Database::connect();

function fetchCurl(string $method, string $url, ?array $post = null, ?string $cookieFile = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'PlexiQ-Beta-Smoke',
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, $resp, $err];
}

function csrfFrom(string $html): ?string
{
    if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $m)) return $m[1];
    if (preg_match('/name="_csrf_token"[^>]*value="([^"]+)"/', $html, $m)) return $m[1];
    if (preg_match('/"csrf_token"\s*:\s*"([^"]+)"/', $html, $m)) return $m[1];
    return null;
}

// --- Login as admin ---
$jar = tempnam(sys_get_temp_dir(), 'pqiq');
[$code, $body] = fetchCurl('GET', "$base/login", null, $jar);
if ($code !== 200) { echo "LOGIN PAGE FAILED: $code\n"; exit(1); }
$token = csrfFrom($body);
if (!$token) { echo "NO CSRF TOKEN ON LOGIN PAGE\n"; exit(1); }
[$code, $body] = fetchCurl('POST', "$base/login", ['username' => 'admin', 'password' => 'admin@123', '_csrf_token' => $token], $jar);
if ($code !== 302) { echo "LOGIN POST FAILED: $code\n"; exit(1); }

[$code] = fetchCurl('GET', "$base/dashboard", null, $jar);
echo "Login OK, dashboard=$code\n\n";

function page(string $path, $jar): array
{
    global $base;
    return fetchCurl('GET', $base . $path, null, $jar);
}

// --- Gather real IDs for detail pages ---
$samples = $db->query("SELECT id FROM samples ORDER BY id LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
$sampleId = $samples[0] ?? null;
$sampleId2 = $samples[1] ?? null;
$batchId = $db->query("SELECT id FROM batches ORDER BY id LIMIT 1")->fetchColumn();
$customerId = $db->query("SELECT id FROM customers ORDER BY id LIMIT 1")->fetchColumn();
$testId = $db->query("SELECT id FROM tests ORDER BY id LIMIT 1")->fetchColumn();
$stId = $db->query("SELECT id FROM sample_tests ORDER BY id LIMIT 1")->fetchColumn();
$stIdInProgress = $db->query("SELECT id FROM sample_tests WHERE status IN ('In Progress','Completed') LIMIT 1")->fetchColumn();
$stIdReviewed = $db->query("SELECT id FROM sample_tests WHERE status = 'Reviewed' LIMIT 1")->fetchColumn();
$stIdApproved = $db->query("SELECT id FROM sample_tests WHERE status = 'Approved' LIMIT 1")->fetchColumn();
$coaSampleId = $db->query("SELECT id FROM samples WHERE status = 'COA Released' LIMIT 1")->fetchColumn();
$oosId = $db->query("SELECT id FROM oos_records ORDER BY id LIMIT 1")->fetchColumn();
$devId = $db->query("SELECT id FROM deviations ORDER BY id LIMIT 1")->fetchColumn();
$capaId = $db->query("SELECT id FROM capa_records ORDER BY id LIMIT 1")->fetchColumn();
$nbId = $db->query("SELECT id FROM eln_notebooks ORDER BY id LIMIT 1")->fetchColumn();
$entryId = $db->query("SELECT id FROM eln_entries ORDER BY id LIMIT 1")->fetchColumn();
$spcParamId = $db->query("SELECT id FROM spc_parameters ORDER BY id LIMIT 1")->fetchColumn();
$qcLotId = $db->query("SELECT id FROM qc_control_lots ORDER BY id LIMIT 1")->fetchColumn();
$envPointId = $db->query("SELECT id FROM environmental_points ORDER BY id LIMIT 1")->fetchColumn();
$supplierId = $db->query("SELECT id FROM suppliers ORDER BY id LIMIT 1")->fetchColumn();
$courseId = $db->query("SELECT id FROM training_courses ORDER BY id LIMIT 1")->fetchColumn();
$instrumentId = $db->query("SELECT id FROM instruments ORDER BY id LIMIT 1")->fetchColumn();
$calStdId = $db->query("SELECT id FROM calibration_standards ORDER BY id LIMIT 1")->fetchColumn();
$chemId = $db->query("SELECT id FROM chemical_inventory ORDER BY id LIMIT 1")->fetchColumn();
$invId = $db->query("SELECT id FROM invoices ORDER BY id LIMIT 1")->fetchColumn();
$projId = $db->query("SELECT id FROM projects ORDER BY id LIMIT 1")->fetchColumn();
$stabId = $db->query("SELECT id FROM stability_studies ORDER BY id LIMIT 1")->fetchColumn();
$userId = $db->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetchColumn();
$coaTemplateId = $db->query("SELECT id FROM coa_templates ORDER BY id LIMIT 1")->fetchColumn();
$pluginId = $db->query("SELECT id FROM plugins ORDER BY id LIMIT 1")->fetchColumn();
$productId = $db->query("SELECT id FROM products ORDER BY id LIMIT 1")->fetchColumn();
$methodId = $db->query("SELECT id FROM methods ORDER BY id LIMIT 1")->fetchColumn();
$apId = $db->query("SELECT id FROM analysis_parameters ORDER BY id LIMIT 1")->fetchColumn();
$emailCfgId = $db->query("SELECT id FROM email_configurations ORDER BY id LIMIT 1")->fetchColumn();
$resultId = $db->query("SELECT id FROM results WHERE uncertainty IS NOT NULL LIMIT 1")->fetchColumn();

// --- Pages to test: [path, expected]
$pages = [
    // Core
    ['/', 200],
    ['/dashboard', 200],
    ['/dashboard/customize', 200],
    ['/workspace', 200],
    ['/workspace/icons', 200],
    ['/profile', 200],
    ['/profile/2fa/setup', 200],
    ['/notifications', 200],
    ['/notifications/settings', 200],
    // Samples / batches / customers
    ['/samples', 200],
    ['/samples/create', 200],
    ['/samples/' . $sampleId, 200],
    ['/samples/' . $sampleId . '/edit', 200],
    ['/samples/' . $sampleId . '/parameters', 200],
    ['/samples/' . $sampleId . '/parameters/entries', 200],
    ['/batches', 200],
    ['/batches/create', 200],
    ['/batches/' . $batchId, 200],
    ['/batches/' . $batchId . '/edit', 200],
    ['/master/customers', 200],
    ['/master/customers/create', 200],
    ['/master/customers/' . $customerId . '/edit', 200],
    // Tests / results workflow
    ['/tests/pending', 200],
    ['/tests/review', 200],
    ['/tests/final-approval', 200],
    ['/tests/' . $stIdInProgress . '/result', 200],
    ['/tests/' . $stIdReviewed . '/result', 200],
    ['/tests/' . $stIdApproved . '/result', 200],
    // Quality
    ['/deviations', 200],
    ['/deviations/create', 200],
    ['/deviations/' . $devId, 200],
    ['/deviations/' . $devId . '/edit', 200],
    ['/oos', 200],
    ['/oos/create', 200],
    ['/oos/' . $oosId, 200],
    ['/oos/' . $oosId . '/edit', 200],
    ['/capa', 200],
    ['/capa/create', 200],
    ['/capa/' . $capaId, 200],
    ['/capa/' . $capaId . '/edit', 200],
    ['/notebooks', 200],
    ['/notebooks/create', 200],
    ['/notebooks/' . $entryId, 200],
    ['/notebooks/' . $entryId . '/edit', 200],
    ['/notebooks/' . $nbId . '/entries/create', 200],
    ['/spc', 200],
    ['/spc/' . $spcParamId, 200],
    ['/spc/' . $spcParamId . '/calculate', 200],
    ['/qc', 200],
    ['/qc/' . $qcLotId, 200],
    ['/qc/' . $qcLotId . '/assess', 200],
    ['/coc', 200],
    ['/stability', 200],
    ['/stability/create', 200],
    ['/stability/' . $stabId, 200],
    ['/stability/' . $stabId . '/edit', 200],
    // Suppliers / training
    ['/suppliers', 200],
    ['/suppliers/create', 200],
    ['/suppliers/' . $supplierId, 200],
    ['/suppliers/' . $supplierId . '/edit', 200],
    ['/suppliers/' . $supplierId . '/products', 200],
    ['/suppliers/' . $supplierId . '/qualifications', 200],
    ['/training', 200],
    ['/training/courses', 200],
    ['/training/courses/create', 200],
    ['/training/courses/' . $courseId . '/edit', 200],
    ['/training/assignments', 200],
    // Instruments / calibration / analysis
    ['/instruments', 200],
    ['/instruments/create', 200],
    ['/instruments/' . $instrumentId . '/edit', 200],
    ['/instruments/' . $instrumentId . '/import', 200],
    ['/instruments/' . $instrumentId . '/mappings', 200],
    ['/instruments/results', 200],
    ['/instruments/imports', 200],
    ['/calibrations', 200],
    ['/calibrations/standards', 200],
    ['/calibrations/standards/create', 200],
    ['/calibrations/standards/' . $calStdId . '/edit', 200],
    ['/calibrations/schedules', 200],
    ['/calibrations/records/' . $instrumentId, 200],
    ['/analysis-parameters', 200],
    ['/analysis-parameters/create', 200],
    ['/analysis-parameters/' . $apId . '/edit', 200],
    ['/master/chemical-inventory', 200],
    ['/master/chemical-inventory/' . $chemId . '/edit', 200],
    // Environment
    ['/environmental', 200],
    ['/environmental/points', 200],
    ['/environmental/points/create', 200],
    ['/environmental/points/' . $envPointId . '/readings', 200],
    ['/environmental/alerts', 200],
    // Compliance / audit / esign
    ['/compliance', 200],
    ['/compliance/privacy-logs', 200],
    ['/compliance/consent-logs', 200],
    ['/compliance/data-retention', 200],
    ['/barcode/logs', 200],
    ['/barcode/scan', 200],
    ['/audit', 200],
    ['/audit/login-history', 200],
    ['/esign/audit', 200],
    // Billing / projects
    ['/billing', 200],
    ['/billing/create', 200],
    ['/billing/' . $invId, 200],
    ['/billing/' . $invId . '/edit', 200],
    ['/billing/' . $invId . '/pdf', 200],
    ['/projects', 200],
    ['/projects/create', 200],
    ['/projects/' . $projId, 200],
    ['/projects/' . $projId . '/edit', 200],
    // Master data
    ['/master', 200],
    ['/master/search', 200],
    ['/master/coa-templates', 200],
    ['/master/coa-templates/' . $coaTemplateId . '/edit', 200],
    ['/master/coa-templates/' . $coaTemplateId . '/preview', 200],
    ['/languages', 200],
    ['/languages/export/' . $userId, 200],
    ['/master/products', 200],
    ['/master/products/create', 200],
    ['/master/products/' . $productId . '/edit', 200],
    ['/master/tests', 200],
    ['/master/tests/create', 200],
    ['/master/tests/' . $testId . '/edit', 200],
    ['/master/methods', 200],
    ['/master/methods/' . $methodId . '/edit', 200],
    ['/master/units', 200],
    ['/master/sample-types', 200],
    ['/master/product-tests', 200],
    ['/master/manufacturers', 200],
    ['/master/manufacturers/create', 200],
    ['/master/manufacturers/' . $userId . '/edit', 200],
    ['/master/instrument-locations', 200],
    ['/master/email-config', 200],
    ['/master/email-config/' . $emailCfgId . '/edit', 200],
    ['/master/calibrations', 200],
    ['/master/calibrations/create', 200],
    // Admin / integrations
    ['/users', 200],
    ['/users/create', 200],
    ['/users/' . $userId . '/edit', 200],
    ['/plugins', 200],
    ['/plugins/' . $pluginId . '/settings', 200],
    ['/backups', 200],
    ['/api-management/tokens', 200],
    ['/api-management/webhooks', 200],
    ['/api-management/webhooks/' . $userId . '/logs', 200],
    ['/sap', 200],
    ['/sap/status', 200],
    ['/sso', 200],
    ['/bi', 200],
    ['/bi/connections', 200],
    ['/deployment', 200],
    ['/installer/builder', 200],
    ['/installer/history', 200],
    // COA
    ['/coa', 200],
    ['/coa/' . $coaSampleId, 200],
    ['/coa/' . $coaSampleId . '/pdf', 200],
    ['/labels/sample/' . $sampleId, 200],
    ['/labels/batch/' . $batchId, 200],
];

$failures = [];
foreach ($pages as [$path, $expect]) {
    [$code, $body] = page($path, $jar);
    $status = $code === $expect ? 'OK' : 'FAIL';
    if ($code !== $expect) {
        $failures[] = [$path, $code, $expect];
    }
    echo "[$status] $path => $code\n";
}

echo "\n===== RESULTS =====\n";
echo "Total pages: " . count($pages) . "\n";
echo "Failures: " . count($failures) . "\n";
foreach ($failures as [$path, $code, $expect]) {
    echo "  FAIL $path => got $code expected $expect\n";
}
if (!$failures) {
    echo "ALL ADMIN PAGES OK\n";
}
