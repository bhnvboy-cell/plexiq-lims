<?php

return [
    // 'database' (default, uses the jobs table) — other drivers can be added
    'driver' => env('QUEUE_DRIVER', 'database'),
    'table' => 'jobs',
    'default_queue' => env('QUEUE_DEFAULT', 'default'),
    // How many times a job is retried before being marked failed
    'max_attempts' => (int)env('QUEUE_MAX_ATTEMPTS', 3),
    // Worker polling interval in seconds
    'sleep' => (int)env('QUEUE_SLEEP', 5),
];
