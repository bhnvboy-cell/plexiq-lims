<?php

namespace App\Models;

use App\BaseModel;

class User extends BaseModel
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';

    public int $id;
    public string $username;
    public string $email;
    public string $password_hash;
    public string $full_name;
    public int $role_id;
    public bool $is_active;
    public ?string $last_login;
    public string $created_at;
    public string $updated_at;

    public static function findByUsername(string $username): ?self
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ?");
        $stmt->execute([$username]);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, static::class);
        return $stmt->fetch() ?: null;
    }

    public static function allWithRole(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
    }

    public static function analysts(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT u.* FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'Analyst' AND u.is_active = TRUE");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
    }

    public static function reviewers(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT u.* FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'Reviewer' AND u.is_active = TRUE");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
    }

    public static function approvers(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT u.* FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'Approver' AND u.is_active = TRUE");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
    }
}
