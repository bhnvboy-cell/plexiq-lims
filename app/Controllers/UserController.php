<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\User;
use App\Models\Role;

class UserController extends BaseController
{
    public function index(): string
    {
        Auth::requireRole('Admin');
        $users = User::allWithRole();
        return $this->render('auth.users', ['users' => $users]);
    }

    public function create(): string
    {
        Auth::requireRole('Admin');
        $roles = Role::all();
        return $this->render('auth.user-form', ['user' => null, 'roles' => $roles]);
    }

    public function store(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();

        $password = $_POST['password'] ?? 'welcome123';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['username'],
            $_POST['email'],
            $hash,
            $_POST['full_name'],
            $_POST['role_id'],
            isset($_POST['is_active']) ? true : false,
        ]);

        $userId = (int)$db->lastInsertId();
        Audit::log('User Created', 'users', $userId);
        session_flash('success', "User created successfully. Default password: {$password}");
        redirect('/users');
    }

    public function edit(int $id): string
    {
        Auth::requireRole('Admin');
        $user = User::find($id);
        if (!$user) { session_flash('error', 'User not found.'); redirect('/users'); }
        $roles = Role::all();
        return $this->render('auth.user-form', ['user' => $user, 'roles' => $roles]);
    }

    public function update(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();

        $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, role_id = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$_POST['username'], $_POST['email'], $_POST['full_name'], $_POST['role_id'], isset($_POST['is_active']) ? true : false];

        if (!empty($_POST['password'])) {
            $sql .= ", password_hash = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Audit::log('User Updated', 'users', $id);
        session_flash('success', 'User updated successfully.');
        redirect('/users');
    }

    public function loginHistory(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("
            SELECT lh.*, u.username, u.full_name
            FROM login_history lh
            JOIN users u ON lh.user_id = u.id
            ORDER BY lh.login_at DESC
            LIMIT 200
        ");
        return $this->render('audit.login-history', ['history' => $stmt->fetchAll()]);
    }
}
