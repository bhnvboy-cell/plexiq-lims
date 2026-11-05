<?php

namespace App;

abstract class BaseModel
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    protected static function getPdo(): \PDO
    {
        return \App\Helpers\Database::connect();
    }

    protected static function validateIdentifier(string $col): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
            throw new \InvalidArgumentException("Invalid SQL identifier: {$col}");
        }
        return $col;
    }

    protected static function normalizeValues(array $values): array
    {
        return array_map(fn($v) => is_bool($v) ? ($v ? 't' : 'f') : $v, $values);
    }

    public static function all(): array
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $stmt = $db->query("SELECT * FROM {$table} ORDER BY created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $pk = static::validateIdentifier(static::$primaryKey);
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE {$pk} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function where(string $column, $value): array
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $column = static::validateIdentifier($column);
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE {$column} = ? ORDER BY created_at DESC");
        $stmt->execute(static::normalizeValues([$value]));
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function create(array $data): ?array
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $cols = array_map(fn($col) => static::validateIdentifier((string)$col), array_keys($data));
        $columns = implode(', ', $cols);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders}) RETURNING *";
        $stmt = $db->prepare($sql);
        $stmt->execute(static::normalizeValues(array_values($data)));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function update(int $id, array $data): bool
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $pk = static::validateIdentifier(static::$primaryKey);
        $sets = implode(', ', array_map(function ($col) {
            $validCol = static::validateIdentifier((string)$col);
            return "{$validCol} = ?";
        }, array_keys($data)));
        $sql = "UPDATE {$table} SET {$sets} WHERE {$pk} = ?";
        $stmt = $db->prepare($sql);
        $values = static::normalizeValues(array_values($data));
        $values[] = $id;
        return $stmt->execute($values);
    }

    public static function delete(int $id): bool
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $pk = static::validateIdentifier(static::$primaryKey);
        $stmt = $db->prepare("DELETE FROM {$table} WHERE {$pk} = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Soft delete (sets deleted_at) when the table has the column.
     * Returns false if the column does not exist.
     */
    public static function softDelete(int $id): bool
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $pk = static::validateIdentifier(static::$primaryKey);
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = ? AND column_name = 'deleted_at'");
        $checkStmt->execute([$table]);
        if ((int)$checkStmt->fetchColumn() === 0) {
            return false;
        }
        $stmt = $db->prepare("UPDATE {$table} SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE {$pk} = ? AND deleted_at IS NULL");
        return $stmt->execute([$id]);
    }

    public static function count(): int
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $stmt = $db->query("SELECT COUNT(*) FROM {$table}");
        return (int)$stmt->fetchColumn();
    }

    public static function paginate(int $perPage = 20, string $where = '', array $params = []): array
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $whereClause = $where ? "WHERE {$where}" : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM {$table} {$whereClause}");
        $countStmt->execute(static::normalizeValues($params));
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("SELECT * FROM {$table} {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute(static::normalizeValues($params));
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
