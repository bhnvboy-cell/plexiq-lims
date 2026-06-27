<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class DashboardCustomController extends BaseController
{
    public function customize(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM dashboard_widgets WHERE user_id = ? ORDER BY widget_order");
        $stmt->execute([Auth::id()]);
        $widgets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $available = $db->query("SELECT * FROM available_widgets WHERE is_active = TRUE ORDER BY category, widget_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('dashboard.customize', [
            'widgets' => $widgets,
            'available' => $available,
        ]);
    }

    public function saveWidgets(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM dashboard_widgets WHERE user_id = ?")->execute([Auth::id()]);
        $widgets = json_decode($_POST['widgets'] ?? '[]', true);
        $order = 0;
        foreach ($widgets as $w) {
            $db->prepare("INSERT INTO dashboard_widgets (user_id, widget_id, widget_config, widget_order, column_index) VALUES (?, ?, ?, ?, ?)")->execute([
                Auth::id(),
                $w['widget_id'],
                json_encode($w['config'] ?? []),
                $order++,
                $w['column'] ?? 0,
            ]);
        }
        Audit::log('Dashboard Customized', 'dashboard_widgets', Auth::id());
        return $this->json(['success' => true]);
    }

    public function saveFilter(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO dashboard_filters (user_id, filter_name, filter_data, is_global) VALUES (?, ?, ?, ?)")->execute([
            Auth::id(),
            $_POST['filter_name'],
            $_POST['filter_data'],
            !empty($_POST['is_global']) && Auth::role() === 'Admin',
        ]);
        session_flash('success', 'Dashboard filter saved.');
        $this->back();
    }

    public function resetWidgets(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM dashboard_widgets WHERE user_id = ?")->execute([Auth::id()]);
        Audit::log('Dashboard Widgets Reset', 'dashboard_widgets', Auth::id());
        return $this->json(['success' => true]);
    }

    public function removeWidget(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM dashboard_widgets WHERE id = ? AND user_id = ?")->execute([$id, Auth::id()]);
        return $this->json(['success' => true]);
    }

    public function deleteFilter(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM dashboard_filters WHERE id = ? AND (user_id = ? OR is_global = FALSE)")->execute([$id, Auth::id()]);
        session_flash('success', 'Filter deleted.');
        $this->back();
    }
}
