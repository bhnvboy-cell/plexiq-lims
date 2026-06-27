<?php

return [
    'connection' => env('DB_CONNECTION', 'pgsql'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'limsdb'),
    'username' => env('DB_USERNAME', 'lims_user'),
    'password' => env('DB_PASSWORD', ''),
];
