<?php
/**
 * Health check endpoint for Docker container monitoring.
 * Returns 200 OK if the application is alive.
 */
header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'application' => 'PlexiQ LIMS',
    'php_version' => PHP_VERSION,
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
        http_response_code(503);
        $health['status'] = 'degraded';
        $health['database'] = 'error: ' . $e->getMessage();
    }
}

$health['memory_usage'] = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';

echo json_encode($health, JSON_PRETTY_PRINT);
