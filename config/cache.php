<?php

return [
    // 'file' (default, no dependencies) or 'redis' (requires ext-redis)
    'driver' => env('CACHE_DRIVER', 'file'),
    'prefix' => env('CACHE_PREFIX', 'plexiq'),
    // Default TTL in seconds
    'default_ttl' => (int)env('CACHE_TTL', 300),
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int)env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int)env('REDIS_DB', 0),
    ],
];
