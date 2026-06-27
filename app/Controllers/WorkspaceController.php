<?php
namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class WorkspaceController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM user_shortcuts WHERE user_id = ? ORDER BY sort_order, id");
        $stmt->execute([Auth::id()]);
        $shortcuts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('workspace.index', ['shortcuts' => $shortcuts]);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();

        $stmt = $db->prepare("
            INSERT INTO user_shortcuts (user_id, title, url, icon, color, sort_order)
            VALUES (?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM user_shortcuts WHERE user_id = ?))
        ");
        $stmt->execute([
            Auth::id(),
            $_POST['title'],
            $_POST['url'],
            $_POST['icon'] ?? 'bi-link',
            $_POST['color'] ?? '#0d6efd',
            Auth::id(),
        ]);

        Audit::log('Shortcut Created', 'user_shortcuts');
        session_flash('success', 'Shortcut added to workspace.');
        redirect('/workspace');
    }

    public function reorder(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $orders = json_decode(file_get_contents('php://input'), true) ?? [];
        $stmt = $db->prepare("UPDATE user_shortcuts SET sort_order = ? WHERE id = ? AND user_id = ?");
        foreach ($orders as $item) {
            if (isset($item['id'], $item['sort_order'])) {
                $stmt->execute([(int)$item['sort_order'], (int)$item['id'], Auth::id()]);
            }
        }
        Audit::log('Shortcuts Reordered', 'user_shortcuts');
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    public function destroy(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("DELETE FROM user_shortcuts WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, Auth::id()]);
        Audit::log('Shortcut Removed', 'user_shortcuts', $id);
        session_flash('success', 'Shortcut removed.');
        redirect('/workspace');
    }

    public function icons(): string
    {
        $icons = [
            'bi-speedometer2' => 'Dashboard',
            'bi-box-seam' => 'Create Batch',
            'bi-boxes' => 'Batch Management',
            'bi-collection' => 'Samples',
            'bi-clipboard-data' => 'Result Entry',
            'bi-check-circle' => 'Review',
            'bi-check-all' => 'Final Approval',
            'bi-bar-chart-steps' => 'SPC Charts',
            'bi-cpu' => 'Instruments',
            'bi-arrow-down-circle' => 'Imported Results',
            'bi-file-text' => 'COA',
            'bi-diagram-3' => 'Projects',
            'bi-exclamation-triangle' => 'OOS',
            'bi-shield-check' => 'CAPA',
            'bi-sliders' => 'Master Data',
            'bi-people' => 'Users',
            'bi-journal-text' => 'Audit Trail',
            'bi-cloud-arrow-up' => 'SAP',
            'bi-link' => 'Custom Link',
            'bi-plus-circle' => 'New',
            'bi-search' => 'Search',
            'bi-gear' => 'Settings',
            'bi-star' => 'Favorite',
            'bi-flag' => 'Flag',
            'bi-activity' => 'Activity',
            'bi-graph-up' => 'Trend',
        ];
        return $this->json($icons);
    }
}
