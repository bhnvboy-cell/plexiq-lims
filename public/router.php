<?php
/**
 * Router script for the PHP built-in dev server.
 * Usage: php -S 0.0.0.0:8080 -t public public/router.php
 *
 * Serves existing static files directly and routes everything else
 * through the front controller (index.php).
 */
$path = urldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve real static files inside public/ as-is (assets, favicon, etc.), preventing path traversal.
$publicRoot = realpath(__DIR__);
$realPath = $path !== '/' ? realpath(__DIR__ . $path) : false;
if ($realPath !== false && $publicRoot !== false && str_starts_with($realPath, $publicRoot . DIRECTORY_SEPARATOR) && is_file($realPath)) {
    return false;
}

require __DIR__ . '/index.php';
