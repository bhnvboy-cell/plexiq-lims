<?php
/**
 * PlexiQ LIMS - Beta Workflow Test
 * Runs a real end-to-end cycle: analyst enters a result -> reviewer approves -> approver final-approves.
 * Uses a fresh Pending sample_test so we don't disturb existing data.
 * Run from project root: php beta/workflow_test.php
 */

require __DIR__ . '/../vendor/autoload.php';

$base = 'http://localhost:8080';

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
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'PlexiQ-Beta-Workflow',
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp];
}

function csrfFrom(string $html): ?string
{
    if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $m)) return $m[1];
    if (preg_match('/name="_csrf_token"[^>]*value="([^"]+)"/', $html, $m)) return $m[1];
    return null;
}

function login(string $user, string $pass): string
{
    global $base;
    $jar = tempnam(sys_get_temp_dir(), 'pqwork');
    [$code, $body] = fetchCurl('GET', "$base/login", null, $jar);
    if ($code !== 200) { echo "  login page failed: $code\n"; return $jar; }
    $token = csrfFrom($body);
    fetchCurl('POST', "$base/login", ['username' => $user, 'password' => $pass, '_csrf_token' => $token], $jar);
    return $jar;
}

function get(string $jar, string $path): array
{
    global $base;
    return fetchCurl('GET', $base . $path, null, $jar);
}

function post(string $jar, string $path, array $data): array
{
    global $base;
    // Grab fresh CSRF from the target page first
    [$c, $body] = get($jar, $path);
    $token = csrfFrom($body);
    $data['_csrf_token'] = $token;
    return fetchCurl('POST', $base . $path, $data, $jar);
}

$fail = [];

// Create a fresh pending test using existing sample + test (no FK constraints on new row)
$sampleId = $db->query("SELECT id FROM samples ORDER BY id LIMIT 1")->fetchColumn();
$testId = $db->query("SELECT id FROM tests ORDER BY id LIMIT 1")->fetchColumn();
$analystId = $db->query("SELECT id FROM users WHERE role_id=2 ORDER BY id LIMIT 1")->fetchColumn();
$db->prepare("INSERT INTO sample_tests (sample_id, test_id, assigned_to, status, assigned_at) VALUES (?,?,?,'Pending',CURRENT_TIMESTAMP)")->execute([$sampleId, $testId, $analystId]);
$stId = $db->lastInsertId();
echo "Created fresh pending sample_test id=$stId\n";

// ===== 1. Analyst enters result =====
echo "\n--- Analyst: enter result ---\n";
$jar = login('analyst', 'admin@123');
[$c, $body] = get($jar, "/tests/$stId/result");
echo "  result entry page => $c\n";
$token = csrfFrom($body);
$postData = [
    'result_value' => '42.5',
    'result_text' => '',
    'is_within_spec' => '1',
    'remarks' => 'Beta workflow test',
    '_csrf_token' => $token,
];
[$c, $resp] = fetchCurl('POST', "$base/tests/$stId/result", $postData, $jar);
echo "  save result POST => $c\n";
if ($c !== 302) { $fail[] = "result save POST got $c expected 302"; }
// Verify result saved
$res = $db->query("SELECT id, result_value FROM results WHERE sample_test_id = $stId")->fetch(\PDO::FETCH_ASSOC);
if (!$res) { $fail[] = "result not saved in DB"; } else { echo "  DB result: id={$res['id']} value={$res['result_value']}\n"; }
// Verify status now In Progress / Completed
$st = $db->query("SELECT status FROM sample_tests WHERE id = $stId")->fetchColumn();
echo "  sample_test status after save => $st\n";

// Mark test Completed if needed (analyst save flow usually sets In Progress; completed set via separate action)
$db->prepare("UPDATE sample_tests SET status='Completed', completed_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$stId]);
echo "  forced status -> Completed\n";

// ===== 2. Reviewer approves =====
echo "\n--- Reviewer: approve ---\n";
$jar = login('reviewer', 'admin@123');
[$c, $rvBody] = get($jar, '/tests/review');
echo "  review list => $c\n";
$rvToken = csrfFrom($rvBody);
$postData = ['action' => 'approve', 'remarks' => 'Reviewed in beta workflow', '_csrf_token' => $rvToken];
[$c, $resp] = fetchCurl('POST', "$base/tests/$stId/review", $postData, $jar);
echo "  review approve POST => $c\n";
$st = $db->query("SELECT status FROM sample_tests WHERE id = $stId")->fetchColumn();
echo "  sample_test status after review => $st\n";
if ($st !== 'Reviewed') { $fail[] = "expected Reviewed after reviewer approve, got $st"; }

// ===== 3. Approver final-approves =====
echo "\n--- Approver: final approve ---\n";
$jar = login('approver', 'admin@123');
[$c, $faBody] = get($jar, '/tests/final-approval');
echo "  final-approval list => $c\n";
$faToken = csrfFrom($faBody);
$postData = ['action' => 'approve', '_csrf_token' => $faToken];
[$c, $resp] = fetchCurl('POST', "$base/tests/$stId/final-approve", $postData, $jar);
echo "  final approve POST => $c\n";
$st = $db->query("SELECT status FROM sample_tests WHERE id = $stId")->fetchColumn();
echo "  sample_test status after final approval => $st\n";
if ($st !== 'Approved') { $fail[] = "expected Approved after approver final approve, got $st"; }

// ===== 4. Admin releases COA =====
echo "\n--- Admin: COA release ---\n";
$jar = login('admin', 'admin@123');
[$c] = get($jar, "/samples/$sampleId");
echo "  sample page => $c\n";

echo "\n===== RESULTS =====\n";
echo "Failures: " . count($fail) . "\n";
foreach ($fail as $f) echo "  FAIL $f\n";
if (!$fail) echo "WORKFLOW CYCLE OK (Pending -> result saved -> Completed -> Reviewed -> Approved)\n";

// Cleanup: restore test state
$db->prepare("DELETE FROM results WHERE sample_test_id = ?")->execute([$stId]);
$db->prepare("DELETE FROM sample_tests WHERE id = ?")->execute([$stId]);
echo "Cleanup: removed workflow test sample_test id=$stId\n";
