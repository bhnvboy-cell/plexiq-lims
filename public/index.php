<?php
/**
 * Advanced LIMS - Entry Point
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/lims-error.log');

// Autoload
require_once __DIR__ . '/../vendor/autoload.php';

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

// Start session (optionally database-backed for multi-instance deployments)
if (session_status() === PHP_SESSION_NONE) {
    if (getenv('SESSION_DRIVER') === 'database' || ($_ENV['SESSION_DRIVER'] ?? '') === 'database') {
        $sessionHandler = new \App\Helpers\SessionHandler();
        session_set_save_handler($sessionHandler, true);
    }
    session_start();
}

// Initialize router
$router = new \App\Router();

// Register middleware
$router->addMiddleware('auth', function () {
    return \App\Middleware\AuthMiddleware::handle();
});
$router->addMiddleware('guest', function () {
    return \App\Middleware\AuthMiddleware::guest();
});
$router->addMiddleware('api', function () {
    return \App\Middleware\ApiAuthMiddleware::authenticate();
});

// Load web routes
require __DIR__ . '/../routes/web.php';

// Load API routes
require __DIR__ . '/../routes/api.php';

// Dispatch request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip subdirectory prefix for apps installed under a subfolder (e.g. /plexiq)
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptDir !== '/' && $scriptDir !== '\\') {
    $uri = '/' . ltrim(substr($uri, strlen($scriptDir)), '/');
    $uri = $uri ?: '/';
}

// Handle CORS for API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $router->dispatch($method, $uri);
} catch (\Exception $e) {
    error_log("LIMS Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo '<h1>500 Internal Server Error</h1>';
    echo '<p>An error occurred. Please check the error log.</p>';
}
