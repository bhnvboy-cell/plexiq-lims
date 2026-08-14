<?php

namespace App\Helpers;

class Pagination
{
    public static function currentPage(): int
    {
        return max(1, (int)($_GET['page'] ?? 1));
    }

    public static function offset(int $page, int $perPage): int
    {
        return max(0, ($page - 1) * $perPage);
    }

    /**
     * Run a paged query.
     *
     * @param string $selectSql  SELECT ... (without LIMIT/OFFSET)
     * @param string $countSql   SELECT COUNT(*) ... (same WHERE)
     */
    public static function run(\PDO $db, string $selectSql, string $countSql, array $params = [], int $perPage = 20, string $orderBy = 'id DESC'): array
    {
        $page = self::currentPage();
        $offset = self::offset($page, $perPage);

        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare($selectSql . " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total,
            'perPage' => $perPage,
            'currentPage' => $page,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        ];
    }
}
