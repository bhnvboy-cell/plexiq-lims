<?php

namespace App;

abstract class BaseModel
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    public static function all(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM " . static::$table . " ORDER BY created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function where(string $column, $value): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE {$column} = ? ORDER BY created_at DESC");
        $stmt->execute([$value]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function create(array $data): ?array
    {
        $db = \App\Helpers\Database::connect();
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO " . static::$table . " ({$columns}) VALUES ({$placeholders}) RETURNING *";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($data));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function update(int $id, array $data): bool
    {
        $db = \App\Helpers\Database::connect();
        $sets = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $sql = "UPDATE " . static::$table . " SET {$sets} WHERE " . static::$primaryKey . " = ?";
        $stmt = $db->prepare($sql);
        $values = array_values($data);
        $values[] = $id;
        return $stmt->execute($values);
    }

    public static function delete(int $id): bool
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        return $stmt->execute([$id]);
    }

    public static function count(): int
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT COUNT(*) FROM " . static::$table);
        return (int)$stmt->fetchColumn();
    }

    public static function paginate(int $perPage = 20, string $where = '', array $params = []): array
    {
        $db = \App\Helpers\Database::connect();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $whereClause = $where ? "WHERE {$where}" : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM " . static::$table . " {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("SELECT * FROM " . static::$table . " {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
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
}
