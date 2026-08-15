<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;

class AuthController extends BaseController
{
    public function showLogin(): string
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        return $this->render('auth.login');
    }

    public function login(): void
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            session_flash('error', 'Username and password are required.');
            redirect('/login');
        }

        $result = Auth::login($username, $password);

        if ($result === '2fa') {
            redirect('/login/2fa');
        }

        if ($result === 'locked') {
            session_flash('error', 'Account temporarily locked due to too many failed attempts. Try again in 15 minutes.');
            redirect('/login');
        }

        if ($result === 'ok') {
            session_flash('success', 'Welcome back, ' . ($_SESSION['user_name'] ?? '') . '!');
            $default = ($_SESSION['user_role'] ?? '') === 'Customer' ? '/client/dashboard' : '/dashboard';
            $redirect = $_SESSION['_redirect_after_login'] ?? $default;
            unset($_SESSION['_redirect_after_login']);
            redirect($redirect);
        }

        session_flash('error', 'Invalid username or password.');
        redirect('/login');
    }

    public function showTwoFactor(): string
    {
        if (empty($_SESSION['2fa_pending_user_id'])) {
            redirect('/login');
        }
        return $this->render('auth.two-factor');
    }

    public function verifyTwoFactor(): void
    {
        $code = $_POST['code'] ?? '';
        if (empty($code)) {
            session_flash('error', 'Please enter your verification code.');
            redirect('/login/2fa');
        }

        if (Auth::verifyTwoFactor($code)) {
            session_flash('success', 'Welcome back, ' . ($_SESSION['user_name'] ?? '') . '!');
            $default = ($_SESSION['user_role'] ?? '') === 'Customer' ? '/client/dashboard' : '/dashboard';
            $redirect = $_SESSION['_redirect_after_login'] ?? $default;
            unset($_SESSION['_redirect_after_login']);
            redirect($redirect);
        }

        session_flash('error', 'Invalid verification code.');
        redirect('/login/2fa');
    }

    public function cancelTwoFactor(): void
    {
        Auth::cancelTwoFactor();
        redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        session_flash('info', 'You have been logged out successfully.');
        redirect('/login');
    }

    public function profile(): string
    {
        Auth::requireAuth();
        $user = Auth::user();
        return $this->render('auth.profile', ['user' => $user]);
    }

    public function setupTwoFactor(): string
    {
        Auth::requireAuth();
        $user = Auth::user();

        // Generate or reuse an unconfirmed secret
        $secret = $_SESSION['2fa_setup_secret'] ?? null;
        if (!$secret || !empty($user['totp_enabled'])) {
            $secret = \App\Helpers\Totp::generateSecret();
            $_SESSION['2fa_setup_secret'] = $secret;
        }

        return $this->render('auth.two-factor-setup', [
            'user' => $user,
            'secret' => $secret,
            'uri' => \App\Helpers\Totp::provisioningUri($secret, $user['username']),
        ]);
    }

    public function enableTwoFactor(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $code = $_POST['code'] ?? '';
        $secret = $_SESSION['2fa_setup_secret'] ?? null;
        $password = $_POST['password'] ?? '';

        if (!$secret) {
            session_flash('error', '2FA setup session expired. Please restart setup.');
            redirect('/profile/2fa/setup');
        }

        $user = Auth::user();
        if (!password_verify($password, $user['password_hash'])) {
            session_flash('error', 'Password confirmation is required to enable 2FA.');
            redirect('/profile/2fa/setup');
        }

        if (!\App\Helpers\Totp::verify($secret, $code)) {
            session_flash('error', 'Invalid verification code. Please try again.');
            redirect('/profile/2fa/setup');
        }

        $stmt = $db->prepare("UPDATE users SET totp_secret = ?, totp_enabled = TRUE, totp_confirmed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$secret, $user['id']]);
        unset($_SESSION['2fa_setup_secret']);

        \App\Helpers\Audit::log('2FA Enabled', 'users', $user['id']);
        session_flash('success', 'Two-factor authentication enabled.');
        redirect('/profile');
    }

    public function disableTwoFactor(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $user = Auth::user();
        $password = $_POST['password'] ?? '';

        if (!password_verify($password, $user['password_hash'])) {
            session_flash('error', 'Password confirmation is required to disable 2FA.');
            redirect('/profile');
        }

        $stmt = $db->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = FALSE, totp_confirmed_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$user['id']]);

        \App\Helpers\Audit::log('2FA Disabled', 'users', $user['id']);
        session_flash('success', 'Two-factor authentication disabled.');
        redirect('/profile');
    }
}
