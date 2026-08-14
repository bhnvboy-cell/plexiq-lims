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

        if (Auth::login($username, $password)) {
            session_flash('success', 'Welcome back, ' . ($_SESSION['user_name'] ?? '') . '!');
            $default = ($_SESSION['user_role'] ?? '') === 'Customer' ? '/client/dashboard' : '/dashboard';
            $redirect = $_SESSION['_redirect_after_login'] ?? $default;
            unset($_SESSION['_redirect_after_login']);
            redirect($redirect);
        }

        session_flash('error', 'Invalid username or password.');
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
}
