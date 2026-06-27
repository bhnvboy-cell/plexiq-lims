<?php
// Test auth exactly like AuthController::login() does
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

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

session_start();

$username = $_POST['username'] ?? 'admin';
$password = $_POST['password'] ?? 'admin@123';

echo "Testing login with username='$username', password='$password'\n\n";

try {
    $db = \App\Helpers\Database::connect();
    $stmt = $db->prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? AND u.is_active = TRUE');
    $stmt->execute([$username]);
    $user = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "User found: {$user['username']}\n";
        echo "Hash: {$user['password_hash']}\n";
        $verify = password_verify($password, $user['password_hash']);
        echo "password_verify result: " . ($verify ? 'TRUE' : 'FALSE') . "\n";
        
        if ($verify) {
            echo "LOGIN WOULD SUCCEED\n";
        } else {
            echo "LOGIN WOULD FAIL (wrong password)\n";
            // Check if hash itself is valid
            $info = password_get_info($user['password_hash']);
            echo "Hash algo: {$info['algoName']}, valid: " . ($info['algo'] > 0 ? 'yes' : 'no') . "\n";
        }
    } else {
        echo "User not found or inactive\n";
        $stmt2 = $db->query('SELECT id, username, is_active FROM users LIMIT 5');
        while ($row = $stmt2->fetch()) {
            echo "  {$row['id']}: {$row['username']} (active: {$row['is_active']})\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
