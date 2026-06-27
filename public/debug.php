<?php
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

try {
    $db = \App\Helpers\Database::connect();
    
    // Check connection
    echo "DB Connected: OK\n";
    
    // Get admin user
    $stmt = $db->prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? AND u.is_active = TRUE');
    $stmt->execute(['admin']);
    $user = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "User found: {$user['username']}\n";
        echo "Hash: {$user['password_hash']}\n";
        
        // Test password
        $testPasswords = ['admin@123', 'password', 'admin'];
        foreach ($testPasswords as $pw) {
            $result = password_verify($pw, $user['password_hash']);
            echo "password_verify('$pw'): " . ($result ? 'TRUE' : 'FALSE') . "\n";
        }
    } else {
        echo "User 'admin' not found\n";
        $stmt = $db->query('SELECT id, username, is_active FROM users LIMIT 5');
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            echo "  - {$row['id']}: {$row['username']} (active: {$row['is_active']})\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
