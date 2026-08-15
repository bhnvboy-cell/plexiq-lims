<?php

namespace App\Models;

use App\BaseModel;

class Notification extends BaseModel
{
    protected static string $table = 'notifications';

    public static function send(int $userId, string $type, string $title, string $message, ?string $link = null, array $options = []): ?array
    {
        $notification = static::create([
            'user_id' => $userId,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'is_read' => 0,
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        // Optionally deliver via email if the user has opted in
        if (($options['send_email'] ?? true) && $notification) {
            self::deliverEmail($userId, $title, $message, $link);
        }

        return $notification;
    }

    /**
     * Deliver a notification email respecting the user's notification settings.
     */
    public static function deliverEmail(int $userId, string $title, string $message, ?string $link = null): void
    {
        $db = \App\Helpers\Database::connect();

        // Load user
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user || empty($user['email'])) {
            return;
        }

        // Respect quiet hours
        $settingsStmt = $db->prepare("SELECT * FROM notification_settings WHERE user_id = ?");
        $settingsStmt->execute([$userId]);
        $settings = $settingsStmt->fetch(\PDO::FETCH_ASSOC);
        if ($settings && empty($settings['email_notifications'])) {
            return;
        }
        if ($settings && !empty($settings['quiet_hours_start']) && !empty($settings['quiet_hours_end'])) {
            $now = (int)date('G');
            $start = (int)substr($settings['quiet_hours_start'], 0, 2);
            $end = (int)substr($settings['quiet_hours_end'], 0, 2);
            if ($start < $end) {
                if ($now >= $start && $now < $end) return;
            } else {
                if ($now >= $start || $now < $end) return;
            }
        }

        $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">'
            . '<div style="background:#0d6efd;color:#fff;padding:16px 24px;"><h3 style="margin:0;">PlexiQ LIMS</h3></div>'
            . '<div style="padding:24px;">'
            . '<h4 style="margin-top:0;">' . htmlspecialchars($title) . '</h4>'
            . '<p>' . nl2br(htmlspecialchars($message)) . '</p>'
            . ($link ? '<p><a href="' . htmlspecialchars($link) . '" style="background:#0d6efd;color:#fff;padding:10px 18px;border-radius:5px;text-decoration:none;display:inline-block;">View Details</a></p>' : '')
            . '<p style="color:#888;font-size:12px;">Sent automatically by PlexiQ LIMS. Do not reply to this email.</p>'
            . '</div></div>';

        $mailer = new \App\Services\Mailer();
        $sent = $mailer->send($user['email'], $title, $html);

        if (!$sent) {
            error_log('PlexiQ email delivery failed: ' . $mailer->lastError());
        }
    }

    public static function getUnreadCount(int $userId): int
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT COUNT(*) FROM " . static::$table . " WHERE (user_id = ? OR user_id IS NULL) AND is_read = FALSE");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function markAsRead(int $id): bool
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("UPDATE " . static::$table . " SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function markAllAsRead(int $userId): bool
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("UPDATE " . static::$table . " SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE (user_id = ? OR user_id IS NULL) AND is_read = FALSE");
        return $stmt->execute([$userId]);
    }
}
