<?php

/*
 * PlexiQ LIMS - Test Bootstrap
 *
 * Initializes the testing environment:
 * 1. Defines testing constants
 * 2. Loads Composer autoloader
 * 3. Loads environment from .env.testing or defaults
 * 4. Initializes session for testing
 * 5. Provides test helper autoloading
 */

define('BASE_PATH', dirname(__DIR__));
define('TESTS_PATH', __DIR__);

// Error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load Composer autoloader
$autoload = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "Composer autoloader not found. Run 'composer install' first.\n";
    exit(1);
}
require_once $autoload;

// Load helpers
require_once BASE_PATH . '/app/Helpers/helpers.php';

// Load test environment if present
$envFile = BASE_PATH . '/.env.testing';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

// Initialize session for CLI testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set testing flag
define('TESTING', true);
