<?php
/**
 * PlexiQ LIMS - Beta Role-Based Flow Test
 * Logs in as each role and verifies their key pages/actions return expected status.
 * Run from project root: php beta/role_test.php
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
        CURLOPT_USERAGENT => 'PlexiQ-Beta-RoleTest',
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
    $jar = tempnam(sys_get_temp_dir(), 'pqrole');
    [$code, $body] = fetchCurl('GET', "$base/login", null, $jar);
    if ($code !== 200) { echo "  login page failed: $code\n"; return $jar; }
    $token = csrfFrom($body);
    [$code] = fetchCurl('POST', "$base/login", ['username' => $user, 'password' => $pass, '_csrf_token' => $token], $jar);
    return $jar;
}

function get(string $jar, string $path): int
{
    global $base;
    [$code] = fetchCurl('GET', $base . $path, null, $jar);
    return $code;
}

// IDs
$sampleId = $db->query("SELECT id FROM samples ORDER BY id LIMIT 1")->fetchColumn();
$stPending = $db->query("SELECT id FROM sample_tests WHERE status = 'Pending' ORDER BY id LIMIT 1")->fetchColumn();
$stInProgress = $db->query("SELECT id FROM sample_tests WHERE status IN ('In Progress','Completed') ORDER BY id LIMIT 1")->fetchColumn();
$stReviewed = $db->query("SELECT id FROM sample_tests WHERE status = 'Reviewed' ORDER BY id LIMIT 1")->fetchColumn();
$stApproved = $db->query("SELECT id FROM sample_tests WHERE status = 'Approved' ORDER BY id LIMIT 1")->fetchColumn();
$coaSampleId = $db->query("SELECT id FROM samples WHERE status = 'COA Released' ORDER BY id LIMIT 1")->fetchColumn();
$coaId = $coaSampleId ?: $db->query("SELECT id FROM coa_documents ORDER BY id LIMIT 1")->fetchColumn();
$customerId = $db->query("SELECT id FROM customers WHERE customer_code NOT LIKE 'BETA-%' ORDER BY id LIMIT 1")->fetchColumn();
$customerId = $customerId ?: $db->query("SELECT id FROM customers ORDER BY id LIMIT 1")->fetchColumn();

$results = [];
$fail = [];

function record(array &$results, array &$fail, string $role, string $page, int $code, int $expect): void
{
    $ok = $code === $expect;
    $results[] = [$role, $page, $code, $expect, $ok];
    if (!$ok) $fail[] = [$role, $page, $code, $expect];
    echo "[" . ($ok ? 'OK' : 'FAIL') . "] $role $page => $code (exp $expect)\n";
}

// ===== ANALYST =====
echo "\n===== ANALYST =====\n";
$jar = login('analyst', 'admin@123');
[$c] = fetchCurl('GET', "$base/dashboard", null, $jar);
echo "  login dashboard => $c\n";
record($results, $fail, 'analyst', '/workspace', get($jar, '/workspace'), 200);
record($results, $fail, 'analyst', '/samples', get($jar, '/samples'), 200);
record($results, $fail, 'analyst', '/samples/' . $sampleId, get($jar, '/samples/' . $sampleId), 200);
record($results, $fail, 'analyst', '/tests/pending', get($jar, '/tests/pending'), 200);
record($results, $fail, 'analyst', '/tests/' . $stPending . '/result', get($jar, '/tests/' . $stPending . '/result'), 200);
record($results, $fail, 'analyst', '/tests/' . $stInProgress . '/result', get($jar, '/tests/' . $stInProgress . '/result'), 200);
record($results, $fail, 'analyst', '/tests/review', get($jar, '/tests/review'), 403);
record($results, $fail, 'analyst', '/tests/final-approval', get($jar, '/tests/final-approval'), 403);
record($results, $fail, 'analyst', '/tests/' . $stReviewed . '/result', get($jar, '/tests/' . $stReviewed . '/result'), 200);
record($results, $fail, 'analyst', '/tests/' . $stApproved . '/result', get($jar, '/tests/' . $stApproved . '/result'), 200);
record($results, $fail, 'analyst', '/users', get($jar, '/users'), 403);
record($results, $fail, 'analyst', '/instruments', get($jar, '/instruments'), 200);
record($results, $fail, 'analyst', '/spc', get($jar, '/spc'), 200);
record($results, $fail, 'analyst', '/qc', get($jar, '/qc'), 200);
record($results, $fail, 'analyst', '/notebooks', get($jar, '/notebooks'), 200);
record($results, $fail, 'analyst', '/deviations', get($jar, '/deviations'), 200);

// ===== REVIEWER =====
echo "\n===== REVIEWER =====\n";
$jar = login('reviewer', 'admin@123');
[$c] = fetchCurl('GET', "$base/dashboard", null, $jar);
echo "  login dashboard => $c\n";
record($results, $fail, 'reviewer', '/tests/review', get($jar, '/tests/review'), 200);
record($results, $fail, 'reviewer', '/tests/' . $stReviewed . '/result', get($jar, '/tests/' . $stReviewed . '/result'), 403);
record($results, $fail, 'reviewer', '/tests/final-approval', get($jar, '/tests/final-approval'), 403);
record($results, $fail, 'reviewer', '/tests/' . $stInProgress . '/result', get($jar, '/tests/' . $stInProgress . '/result'), 403);
record($results, $fail, 'reviewer', '/users', get($jar, '/users'), 403);
record($results, $fail, 'reviewer', '/spc', get($jar, '/spc'), 200);

// ===== APPROVER =====
echo "\n===== APPROVER =====\n";
$jar = login('approver', 'admin@123');
[$c] = fetchCurl('GET', "$base/dashboard", null, $jar);
echo "  login dashboard => $c\n";
record($results, $fail, 'approver', '/tests/final-approval', get($jar, '/tests/final-approval'), 200);
record($results, $fail, 'approver', '/tests/' . $stApproved . '/result', get($jar, '/tests/' . $stApproved . '/result'), 403);
record($results, $fail, 'approver', '/tests/review', get($jar, '/tests/review'), 403);
record($results, $fail, 'approver', '/coa', get($jar, '/coa'), 200);
record($results, $fail, 'approver', '/coa/' . $coaSampleId, get($jar, '/coa/' . $coaSampleId), 200);
record($results, $fail, 'approver', '/users', get($jar, '/users'), 403);
record($results, $fail, 'approver', '/backups', get($jar, '/backups'), 403);

// ===== CUSTOMER =====
echo "\n===== CUSTOMER =====\n";
$jar = login('customer', 'admin@123');
[$c] = fetchCurl('GET', "$base/dashboard", null, $jar);
echo "  login redirect dashboard => $c\n";
$coaIdCust1 = $db->query("SELECT cd.id FROM coa_documents cd JOIN samples s ON cd.sample_id=s.id WHERE s.customer_id=1 ORDER BY cd.id LIMIT 1")->fetchColumn();
record($results, $fail, 'customer', '/dashboard', get($jar, '/dashboard'), 200);
record($results, $fail, 'customer', '/client/dashboard', get($jar, '/client/dashboard'), 200);
record($results, $fail, 'customer', '/client/coa/' . $coaIdCust1, get($jar, '/client/coa/' . $coaIdCust1), 200);
record($results, $fail, 'customer', '/client/coa/' . $coaIdCust1 . '/pdf', get($jar, '/client/coa/' . $coaIdCust1 . '/pdf'), 200);
record($results, $fail, 'customer', '/samples', get($jar, '/samples'), 403);
record($results, $fail, 'customer', '/users', get($jar, '/users'), 403);

echo "\n===== RESULTS =====\n";
$total = count($results);
echo "Total checks: $total\n";
echo "Failures: " . count($fail) . "\n";
foreach ($fail as [$role, $page, $code, $expect]) {
    echo "  FAIL $role $page => got $code expected $expect\n";
}
if (!$fail) echo "ALL ROLE CHECKS OK\n";
