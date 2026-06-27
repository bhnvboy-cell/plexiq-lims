<?php
// Fallback for XAMPP - route all requests through public/
$uri = $_SERVER['REQUEST_URI'];
if (preg_match('#^/plexiq(/.*)$#', $uri, $m)) {
    $_SERVER['REQUEST_URI'] = $m[1];
}
$_SERVER['SCRIPT_NAME'] = '/plexiq/public/index.php';
return require __DIR__ . '/public/index.php';
