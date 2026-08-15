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

    /**
     * Attempt login. Returns:
     *  - 'ok'      : fully authenticated
     *  - 'locked'  : account temporarily locked
     *  - '2fa'     : password verified, TOTP required
     *  - 'invalid' : bad credentials
     */
    public static function login(string $username, string $password): string
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare('SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? AND u.is_active = TRUE');
        $stmt->execute([$username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            return 'invalid';
        }

        // Account lockout check
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            return 'locked';
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt($user['id']);
            \App\Helpers\Audit::log('Failed Login', 'users', $user['id'], null, ['username' => $username]);
            return 'invalid';
        }

        // Successful password: clear failure counter
        $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$user['id']]);

        // 2FA gate: stage the user, require TOTP before establishing session
        if (!empty($user['totp_enabled']) && !empty($user['totp_secret'])) {
            session_regenerate_id(true);
            $_SESSION['2fa_pending_user_id'] = (int)$user['id'];
            $_SESSION['2fa_pending_username'] = $user['username'];
            $_SESSION['2fa_attempts'] = 0;
            return '2fa';
        }

        self::completeLogin($user, $username);
        return 'ok';
    }

    /**
     * Verify a TOTP code for the staged 2FA login and establish the session.
     */
    public static function verifyTwoFactor(string $code): bool
    {
        $userId = $_SESSION['2fa_pending_user_id'] ?? null;
        $username = $_SESSION['2fa_pending_username'] ?? '';
        if (!$userId) {
            return false;
        }

        if (($_SESSION['2fa_attempts'] ?? 0) >= 5) {
            unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_username'], $_SESSION['2fa_attempts']);
            return false;
        }

        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user || empty($user['totp_secret']) || empty($user['totp_enabled'])) {
            return false;
        }

        if (!Totp::verify($user['totp_secret'], $code)) {
            $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;
            \App\Helpers\Audit::log('2FA Failed', 'users', $userId, null, ['username' => $username]);
            return false;
        }

        $stmt = $db->prepare("SELECT r.name as role_name FROM roles r WHERE r.id = ?");
        $stmt->execute([$user['role_id']]);
        $user['role_name'] = $stmt->fetch(\PDO::FETCH_ASSOC)['role_name'] ?? '';

        unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_username'], $_SESSION['2fa_attempts']);
        self::completeLogin($user, $username);
        \App\Helpers\Audit::log('2FA Verified', 'users', $user['id'], null, ['username' => $username]);
        return true;
    }

    private static function completeLogin(array $user, string $username): void
    {
        $db = \App\Helpers\Database::connect();
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
    }

    private static function recordFailedAttempt(int $userId): void
    {
        $db = \App\Helpers\Database::connect();
        $maxAttempts = 5;
        $lockMinutes = 15;
        $db->prepare("UPDATE users SET failed_login_attempts = COALESCE(failed_login_attempts, 0) + 1,
            locked_until = CASE WHEN COALESCE(failed_login_attempts, 0) + 1 >= ? THEN NOW() + (? * INTERVAL '1 minute') ELSE locked_until END
            WHERE id = ?")->execute([$maxAttempts, $lockMinutes, $userId]);
    }

    public static function cancelTwoFactor(): void
    {
        unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_username'], $_SESSION['2fa_attempts']);
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
