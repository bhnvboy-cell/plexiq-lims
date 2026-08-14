<?php

return [
    // 'file' (default, PHP native) or 'database' (shared across instances)
    'driver' => env('SESSION_DRIVER', 'file'),
    'table' => 'sessions',
    // GC lifetime in seconds
    'lifetime' => (int)env('SESSION_LIFETIME', 7200),
];
