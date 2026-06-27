<?php

namespace App\Models;

use App\BaseModel;

class Notification extends BaseModel
{
    protected static string $table = 'notifications';

    public static function send(int $userId, string $type, string $title, string $message, ?string $link = null): ?array
    {
        return static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'is_read' => 0,
        ]);
    }

    public static function getUnreadCount(int $userId): int
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT COUNT(*) FROM " . static::$table . " WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function markAsRead(int $id): bool
    {
        return static::update($id, ['is_read' => 1]);
    }

    public static function markAllAsRead(int $userId): bool
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("UPDATE " . static::$table . " SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$userId]);
    }
}
