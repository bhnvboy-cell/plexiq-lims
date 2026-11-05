<?php
/**
 * Health check endpoint for Docker container monitoring.
 * Returns 200 OK if the application is alive.
 */
header('Content-Type: application/json');

// Load .env if DB_HOST is not already in environment
if (!getenv('DB_HOST')) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'application' => 'PlexiQ LIMS',
];

// Check database connectivity if env is set
$dbHost = getenv('DB_HOST');
if ($dbHost) {
    try {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $dbHost,
            getenv('DB_PORT') ?: '5432',
            getenv('DB_DATABASE') ?: 'limsdb'
        );
        $pdo = new PDO($dsn, getenv('DB_USERNAME') ?: 'lims_user', getenv('DB_PASSWORD') ?: '');
        $pdo->query('SELECT 1');
        $health['database'] = 'connected';
    } catch (\Exception $e) {
        error_log('Health check DB error: ' . $e->getMessage());
        http_response_code(503);
        $health['status'] = 'degraded';
        $health['database'] = 'error';
    }
}

echo json_encode($health, JSON_PRETTY_PRINT);
