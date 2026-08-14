<?php

namespace App\Models;

use App\BaseModel;

class Sample extends BaseModel
{
    protected static string $table = 'samples';
    protected static string $primaryKey = 'id';

    public static function generateCode(): string
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT nextval('sample_code_seq')");
        $seq = $stmt->fetchColumn();
        return 'SMP-' . date('Ymd') . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public static function withRelations(int $id): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT s.*,
                   c.customer_name, c.customer_code,
                   p.product_name, p.product_code,
                   a.full_name AS analyst_name,
                   r.full_name AS reviewer_name,
                   ap.full_name AS approver_name,
                   reg.full_name AS registered_by_name
            FROM samples s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN users a ON s.assigned_analyst_id = a.id
            LEFT JOIN users r ON s.assigned_reviewer_id = r.id
            LEFT JOIN users ap ON s.assigned_approver_id = ap.id
            LEFT JOIN users reg ON s.registered_by = reg.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function withAllRelations(array $filters = [], int $perPage = 20): array
    {
        $db = \App\Helpers\Database::connect();
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 's.customer_id = ?';
            $params[] = $filters['customer_id'];
        }
        if (!empty($filters['product_id'])) {
            $where[] = 's.product_id = ?';
            $params[] = $filters['product_id'];
        }
        if (!empty($filters['analyst_id'])) {
            $where[] = 's.assigned_analyst_id = ?';
            $params[] = $filters['analyst_id'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 's.priority = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(s.sample_code ILIKE ? OR s.batch_number ILIKE ? OR c.customer_name ILIKE ? OR p.product_name ILIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $countStmt = $db->prepare("
            SELECT COUNT(*) FROM samples s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            {$whereClause}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT s.*, c.customer_name, p.product_name, u.full_name AS analyst_name
            FROM samples s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.assigned_analyst_id = u.id
            {$whereClause}
            ORDER BY s.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'perPage' => $perPage,
            'currentPage' => $page,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public static function dashboardStats(): array
    {
        return \App\Helpers\Cache::remember('dashboard.stats', 60, function () {
            $db = \App\Helpers\Database::connect();
            $stats = [];

            $stats['total'] = (int)$db->query("SELECT COUNT(*) FROM samples")->fetchColumn();
            $stats['registered'] = (int)$db->query("SELECT COUNT(*) FROM samples WHERE status = 'Registered'")->fetchColumn();
            $stats['in_progress'] = (int)$db->query("SELECT COUNT(*) FROM samples WHERE status = 'In Progress'")->fetchColumn();
            $stats['reviewed'] = (int)$db->query("SELECT COUNT(*) FROM samples WHERE status = 'Reviewed'")->fetchColumn();
            $stats['approved'] = (int)$db->query("SELECT COUNT(*) FROM samples WHERE status = 'Approved'")->fetchColumn();
            $stats['coa_released'] = (int)$db->query("SELECT COUNT(*) FROM samples WHERE status = 'COA Released'")->fetchColumn();
            $stats['urgent'] = (int)$db->query("SELECT COUNT(*) FROM samples WHERE priority = 'Urgent' AND status NOT IN ('COA Released','Rejected')")->fetchColumn();
            $stats['overdue'] = (int)$db->query("SELECT COUNT(*) FROM samples WHERE target_completion_date < CURRENT_DATE AND status NOT IN ('COA Released','Rejected')")->fetchColumn();

            return $stats;
        });
    }

    public static function flushDashboardCache(): void
    {
        \App\Helpers\Cache::forget('dashboard.stats');
    }
}
