<?php

namespace App\Helpers;

class Auth
{
    public static function check(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // 15-minute idle timeout check if session activity tracking is enabled
        if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity'] > 900)) {
            self::logout();
            return false;
        }
        if (isset($_SESSION['_last_activity'])) {
            $_SESSION['_last_activity'] = time();
        }

        return true;
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

        // 2FA gate: stage the user, require TOTP before establishing session
        // Defer resetting failed_login_attempts until 2FA verification succeeds
        if (!empty($user['totp_enabled']) && !empty($user['totp_secret'])) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['2fa_pending_user_id'] = (int)$user['id'];
            $_SESSION['2fa_pending_username'] = $user['username'];
            $_SESSION['2fa_attempts'] = 0;
            return '2fa';
        }

        // Successful password (no 2FA): clear failure counter
        $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$user['id']]);

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
        $stmt = $db->prepare("SELECT u.* FROM users u WHERE u.id = ? AND u.is_active = TRUE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user || empty($user['totp_secret']) || empty($user['totp_enabled'])) {
            return false;
        }

        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_username'], $_SESSION['2fa_attempts']);
            return false;
        }

        if (!Totp::verify($user['totp_secret'], $code)) {
            $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;
            self::recordFailedAttempt((int)$userId);
            \App\Helpers\Audit::log('2FA Failed', 'users', (int)$userId, null, ['username' => $username]);
            if ($_SESSION['2fa_attempts'] >= 5) {
                unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_username'], $_SESSION['2fa_attempts']);
            }
            return false;
        }

        $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$user['id']]);

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
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        if (function_exists('csrf_regenerate')) {
            csrf_regenerate();
        }
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_role'] = $user['role_name'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['_last_activity'] = time();
        if (!empty($user['must_change_password'])) {
            $_SESSION['_must_change_password'] = true;
        }

        // Update last login and track active session ID for concurrent session control
        $stmt = $db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP, active_session_id = ? WHERE id = ?');
        $stmt->execute([session_id(), $user['id']]);

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
            try {
                $db = \App\Helpers\Database::connect();
                $stmt = $db->prepare('UPDATE login_history SET logout_at = CURRENT_TIMESTAMP WHERE session_id = ? AND logout_at IS NULL');
                $stmt->execute([session_id()]);

                \App\Helpers\Audit::log('Logout', 'users', self::id(), null, ['username' => $_SESSION['user_username'] ?? '']);
            } catch (\Throwable $e) {
                if (!defined('TESTING') || !TESTING) {
                    error_log('Logout DB error: ' . $e->getMessage());
                }
            }
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies') && !headers_sent()) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            $reqUri = $_SERVER['REQUEST_URI'] ?? '/';
            if (is_string($reqUri) && str_starts_with($reqUri, '/') && !str_starts_with($reqUri, '//')) {
                $_SESSION['_redirect_after_login'] = $reqUri;
            } else {
                $_SESSION['_redirect_after_login'] = '/dashboard';
            }
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
            if (!headers_sent()) {
                http_response_code(403);
            }
            echo view('errors.403');
            exit;
        }
    }

    /**
     * Check if current user has a specific granular permission.
     */
    public static function can(string $permissionCode, ?int $siteId = null): bool
    {
        if (!self::check()) {
            return false;
        }

        // Fast-path cached permissions in session
        if (isset($_SESSION['_permissions'][$permissionCode])) {
            return (bool)$_SESSION['_permissions'][$permissionCode];
        }

        $userId = self::id();
        $userRole = self::role();

        // System Admin has all permissions by default (except segregation-of-duties rules)
        if ($userRole === 'Admin' || $userRole === 'Administrator') {
            $_SESSION['_permissions'][$permissionCode] = true;
            return true;
        }

        try {
            $db = \App\Helpers\Database::connect();

            // 1. Check direct user override (user_permissions)
            $stmtUser = $db->prepare("
                SELECT up.granted FROM user_permissions up
                JOIN permissions p ON up.permission_id = p.id
                WHERE up.user_id = ? AND p.code = ?
                LIMIT 1
            ");
            $stmtUser->execute([$userId, $permissionCode]);
            $userPerm = $stmtUser->fetch(\PDO::FETCH_ASSOC);
            if ($userPerm !== false) {
                $granted = (bool)$userPerm['granted'];
                $_SESSION['_permissions'][$permissionCode] = $granted;
                return $granted;
            }

            // 2. Check role permission
            $user = self::user();
            if ($user && !empty($user['role_id'])) {
                $stmtRole = $db->prepare("
                    SELECT 1 FROM role_permissions rp
                    JOIN permissions p ON rp.permission_id = p.id
                    WHERE rp.role_id = ? AND p.code = ?
                    LIMIT 1
                ");
                $stmtRole->execute([$user['role_id'], $permissionCode]);
                $granted = (bool)$stmtRole->fetchColumn();
                $_SESSION['_permissions'][$permissionCode] = $granted;
                return $granted;
            }
        } catch (\Throwable $e) {
            // If permissions table not yet migrated, fall back gracefully
        }

        return false;
    }

    /**
     * Enforce a granular permission or return 403.
     */
    public static function requirePermission(string $permissionCode): void
    {
        self::requireAuth();
        if (!self::can($permissionCode)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            echo view('errors.403');
            exit;
        }
    }

    /**
     * Strict 21 CFR Part 11 / GxP Segregation of Duties.
     * Prevents an analyst from reviewing or approving their own results, with zero admin bypass.
     */
    public static function verifySegregationOfDuties(string $action, array $record): bool
    {
        $userId = self::id();
        if (!$userId) {
            return false;
        }

        $enteredBy = isset($record['entered_by']) ? (int)$record['entered_by'] : null;
        $reviewedBy = isset($record['reviewed_by']) ? (int)$record['reviewed_by'] : null;

        if ($action === 'review') {
            // 4-Eyes Principle: Reviewer cannot be the author of the result
            return $enteredBy === null || $enteredBy !== $userId;
        }

        if ($action === 'approve') {
            // Approver cannot be the author or reviewer of the result
            if ($enteredBy !== null && $enteredBy === $userId) {
                return false;
            }
            if ($reviewedBy !== null && $reviewedBy === $userId) {
                return false;
            }
            return true;
        }

        return true;
    }

    /**
     * Validate password complexity against regulated GxP standards:
     * Minimum 10 characters, at least 1 uppercase, 1 lowercase, 1 digit, 1 special character.
     */
    public static function validatePasswordPolicy(string $password): array
    {
        $errors = [];
        if (strlen($password) < 10) {
            $errors[] = 'Password must be at least 10 characters long.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one digit.';
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>_\-\=\+]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }
        return $errors;
    }

    /**
     * Verify that new password does not match any of the user's previous N passwords.
     */
    public static function checkPasswordHistory(int $userId, string $newPassword, int $historyLimit = 5): bool
    {
        try {
            $db = \App\Helpers\Database::connect();
            $stmt = $db->prepare("SELECT password_hash FROM password_histories WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$userId, $historyLimit]);
            $hashes = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($hashes as $hash) {
                if (password_verify($newPassword, $hash)) {
                    return false; // Password has been used recently
                }
            }
        } catch (\Throwable $e) {
            // Table may not yet exist
        }
        return true;
    }

    /**
     * Record a password hash in the history ledger.
     */
    public static function recordPasswordHistory(int $userId, string $passwordHash): void
    {
        try {
            $db = \App\Helpers\Database::connect();
            $stmt = $db->prepare("INSERT INTO password_histories (user_id, password_hash, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$userId, $passwordHash]);
        } catch (\Throwable $e) {
            // Table may not yet exist
        }
    }
}
