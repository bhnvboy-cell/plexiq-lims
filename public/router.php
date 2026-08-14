<?php
/**
 * Router script for the PHP built-in dev server.
 * Usage: php -S 0.0.0.0:8080 -t public public/router.php
 *
 * Serves existing static files directly and routes everything else
 * through the front controller (index.php).
 */
$path = urldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve real static files as-is (assets, favicon, etc.).
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/index.php';
