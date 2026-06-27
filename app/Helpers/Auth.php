<?php

namespace App\Helpers;

class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND u.is_active = TRUE');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public static function hasRole(string $role): bool
    {
        return self::role() === $role;
    }

    public static function hasAnyRole(array $roles): bool
    {
        return in_array(self::role(), $roles);
    }

    public static function login(string $username, string $password): bool
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? AND u.is_active = TRUE');
        $stmt->execute([$username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_role'] = $user['role_name'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_username'] = $user['username'];

            // Update last login
            $stmt = $db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$user['id']]);

            // Log login history
            $stmt = $db->prepare('INSERT INTO login_history (user_id, ip_address, user_agent, session_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $_SERVER['HTTP_USER_AGENT'] ?? '', session_id()]);

            // Audit log
            \App\Helpers\Audit::log('Login', 'users', $user['id'], null, ['username' => $username]);

            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        if (self::check()) {
            $db = \App\Helpers\Database::connect();
            $stmt = $db->prepare('UPDATE login_history SET logout_at = CURRENT_TIMESTAMP WHERE session_id = ? AND logout_at IS NULL');
            $stmt->execute([session_id()]);

            \App\Helpers\Audit::log('Logout', 'users', self::id(), null, ['username' => $_SESSION['user_username'] ?? '']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            $_SESSION['_redirect_after_login'] = $_SERVER['REQUEST_URI'];
            redirect('/login');
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireAuth();
        if (!self::hasRole($role)) {
            http_response_code(403);
            echo view('errors.403');
            exit;
        }
    }

    public static function requireAnyRole(array $roles): void
    {
        self::requireAuth();
        if (!self::hasAnyRole($roles)) {
            http_response_code(403);
            echo view('errors.403');
            exit;
        }
    }
}
