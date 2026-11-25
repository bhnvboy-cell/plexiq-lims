<?php

namespace App;

abstract class BaseModel
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static bool $audited = true;

    protected static function shouldAudit(string $table): bool
    {
        return static::$audited
            && !str_starts_with($table, 'test_')
            && !in_array($table, ['audit_logs', 'login_history', 'audit_verifications'], true);
    }

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

    public static function create(array $data, ?string $reason = null): ?array
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

        if ($result && static::shouldAudit($table)) {
            $pk = static::$primaryKey;
            $id = isset($result[$pk]) ? (int)$result[$pk] : null;
            \App\Helpers\Audit::log('Create', $table, $id, null, $result, $reason);
        }

        return $result ?: null;
    }

    public static function update(int $id, array $data, ?string $reason = null): bool
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $pk = static::validateIdentifier(static::$primaryKey);

        $old = null;
        if (static::shouldAudit($table)) {
            try {
                $old = static::find($id);
            } catch (\Throwable $e) {}
        }

        $sets = implode(', ', array_map(function ($col) {
            $validCol = static::validateIdentifier((string)$col);
            return "{$validCol} = ?";
        }, array_keys($data)));
        $sql = "UPDATE {$table} SET {$sets} WHERE {$pk} = ?";
        $stmt = $db->prepare($sql);
        $values = static::normalizeValues(array_values($data));
        $values[] = $id;
        $success = $stmt->execute($values);

        if ($success && static::shouldAudit($table)) {
            \App\Helpers\Audit::log('Update', $table, $id, $old, $data, $reason);
        }

        return $success;
    }

    public static function delete(int $id, ?string $reason = null): bool
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $pk = static::validateIdentifier(static::$primaryKey);

        $old = null;
        if (static::shouldAudit($table)) {
            try {
                $old = static::find($id);
            } catch (\Throwable $e) {}
        }

        $stmt = $db->prepare("DELETE FROM {$table} WHERE {$pk} = ?");
        $success = $stmt->execute([$id]);

        if ($success && static::shouldAudit($table)) {
            \App\Helpers\Audit::log('Delete', $table, $id, $old, null, $reason);
        }

        return $success;
    }

    /**
     * Soft delete (sets deleted_at) when the table has the column.
     * Returns false if the column does not exist.
     */
    public static function softDelete(int $id, ?string $reason = null): bool
    {
        $db = static::getPdo();
        $table = static::validateIdentifier(static::$table);
        $pk = static::validateIdentifier(static::$primaryKey);
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = ? AND column_name = 'deleted_at'");
        $checkStmt->execute([$table]);
        if ((int)$checkStmt->fetchColumn() === 0) {
            return false;
        }

        $old = null;
        if (static::shouldAudit($table)) {
            try {
                $old = static::find($id);
            } catch (\Throwable $e) {}
        }

        $stmt = $db->prepare("UPDATE {$table} SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE {$pk} = ? AND deleted_at IS NULL");
        $success = $stmt->execute([$id]);

        if ($success && static::shouldAudit($table)) {
            \App\Helpers\Audit::log('Soft Delete', $table, $id, $old, ['deleted_at' => date('Y-m-d H:i:s')], $reason);
        }

        return $success;
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
