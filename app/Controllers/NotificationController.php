<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class NotificationController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT n.*, u.full_name AS triggered_by_name
            FROM notifications n
            LEFT JOIN users u ON n.triggered_by = u.id
            WHERE n.user_id = ? OR n.user_id IS NULL
            ORDER BY n.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([Auth::id()]);
        $notifications = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $unread = $db->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = FALSE");
        $unread->execute([Auth::id()]);
        return $this->render('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => (int)$unread->fetchColumn(),
        ]);
    }

    public function markRead(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE id = ? AND (user_id = ? OR user_id IS NULL)")->execute([$id, Auth::id()]);
        return $this->json(['success' => true]);
    }

    public function markAllRead(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE (user_id = ? OR user_id IS NULL) AND is_read = FALSE")->execute([Auth::id()]);
        session_flash('success', 'All notifications marked as read.');
        $this->redirect('/notifications');
    }

    public function settings(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM notification_settings WHERE user_id = ?");
        $stmt->execute([Auth::id()]);
        $settings = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$settings) {
            $db->prepare("INSERT INTO notification_settings (user_id) VALUES (?)")->execute([Auth::id()]);
            $settings = [
                'email_notifications' => true,
                'browser_notifications' => true,
                'digest_frequency' => 'daily',
                'notify_on_sample_status' => true,
                'notify_on_result_entry' => true,
                'notify_on_certificate' => true,
                'notify_on_deviation' => true,
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
            ];
        }
        return $this->render('notifications.settings', ['settings' => $settings]);
    }

    public function updateSettings(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE notification_settings SET email_notifications = ?, browser_notifications = ?, digest_frequency = ?, notify_on_sample_status = ?, notify_on_result_entry = ?, notify_on_certificate = ?, notify_on_deviation = ?, quiet_hours_start = ?, quiet_hours_end = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?")->execute([
            !empty($_POST['email_notifications']),
            !empty($_POST['browser_notifications']),
            $_POST['digest_frequency'] ?? 'daily',
            !empty($_POST['notify_on_sample_status']),
            !empty($_POST['notify_on_result_entry']),
            !empty($_POST['notify_on_certificate']),
            !empty($_POST['notify_on_deviation']),
            $_POST['quiet_hours_start'] ?: null,
            $_POST['quiet_hours_end'] ?: null,
            Auth::id(),
        ]);
        Audit::log('Notification Settings Updated', 'notification_settings', Auth::id());
        session_flash('success', 'Notification settings saved.');
        $this->redirect('/notifications/settings');
    }

    public function sendTest(): string
    {
        Auth::requireAuth();
        $user = Auth::user();
        if ($user && !empty($user['email'])) {
            // Log test notification; actual email would use a mailer service
            Audit::log('Test Notification Sent', 'notifications', null, null, ['user_id' => Auth::id(), 'email' => $user['email'] ?? '']);
        }
        return $this->json(['success' => true, 'message' => 'Test notification logged.']);
    }
}
