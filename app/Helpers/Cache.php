<?php

namespace App\Helpers;

/**
 * Simple cache abstraction.
 *
 * Drivers:
 *   - file   : per-key PHP files in storage/cache (default, zero dependencies)
 *   - redis  : requires ext-redis
 */
class Cache
{
    public static function get(string $key, $default = null)
    {
        if (self::driver() === 'redis') {
            return self::redisGet($key, $default);
        }
        $path = self::filePath($key);
        if (!is_file($path)) {
            return $default;
        }
        $payload = @unserialize(@file_get_contents($path));
        if (!is_array($payload)) {
            return $default;
        }
        if ($payload['expires'] !== null && $payload['expires'] < time()) {
            @unlink($path);
            return $default;
        }
        return $payload['value'];
    }

    public static function put(string $key, $value, ?int $ttl = null): void
    {
        $ttl = $ttl ?? (int)config('cache.default_ttl', 300);
        if (self::driver() === 'redis') {
            self::redisPut($key, $value, $ttl);
            return;
        }
        $dir = storage_path('cache');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $payload = serialize([
            'expires' => $ttl > 0 ? time() + $ttl : null,
            'value' => $value,
        ]);
        @file_put_contents(self::filePath($key), $payload, LOCK_EX);
    }

    public static function remember(string $key, $ttl, callable $callback)
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        self::put($key, $value, is_callable($ttl) ? null : $ttl);
        return $value;
    }

    public static function forget(string $key): void
    {
        if (self::driver() === 'redis') {
            $redis = self::redis();
            if ($redis) {
                $redis->del(self::redisKey($key));
            }
            return;
        }
        $path = self::filePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function flush(): void
    {
        if (self::driver() === 'redis') {
            $redis = self::redis();
            if ($redis) {
                $redis->flushDb();
            }
            return;
        }
        $dir = storage_path('cache');
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') as $file) {
                @unlink($file);
            }
        }
    }

    public static function driver(): string
    {
        return strtolower((string)config('cache.driver', 'file'));
    }

    private static function filePath(string $key): string
    {
        return storage_path('cache') . '/' . sha1((string)config('cache.prefix', 'plexiq') . ':' . $key) . '.cache';
    }

    private static function redisKey(string $key): string
    {
        return (string)config('cache.prefix', 'plexiq') . ':' . $key;
    }

    private static function redis(): ?\Redis
    {
        static $redis = null;
        if ($redis === null) {
            if (!class_exists(\Redis::class)) {
                return null;
            }
            try {
                $cfg = config('cache.redis', []);
                $redis = new \Redis();
                $redis->connect($cfg['host'] ?? '127.0.0.1', (int)($cfg['port'] ?? 6379));
                if (!empty($cfg['password'])) {
                    $redis->auth($cfg['password']);
                }
                if (isset($cfg['database'])) {
                    $redis->select((int)$cfg['database']);
                }
            } catch (\Throwable $e) {
                $redis = null;
            }
        }
        return $redis;
    }

    private static function redisGet(string $key, $default = null)
    {
        $redis = self::redis();
        if (!$redis) {
            return $default;
        }
        $value = $redis->get(self::redisKey($key));
        return $value === false ? $default : unserialize($value);
    }

    private static function redisPut(string $key, $value, int $ttl): void
    {
        $redis = self::redis();
        if (!$redis) {
            return;
        }
        $redisKey = self::redisKey($key);
        if ($ttl > 0) {
            $redis->setex($redisKey, $ttl, serialize($value));
        } else {
            $redis->set($redisKey, serialize($value));
        }
    }
}
