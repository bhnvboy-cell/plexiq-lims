<?php

namespace App\Helpers;

class Logger
{
    public static function log(string $level, string $message, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'user_id' => Auth::id() ?? ($_REQUEST['_api_user_id'] ?? null),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        ];

        $json = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        $logDir = storage_path('logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . DIRECTORY_SEPARATOR . 'plexiq-' . date('Y-m-d') . '.log';
        @file_put_contents($logFile, $json, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }
}
