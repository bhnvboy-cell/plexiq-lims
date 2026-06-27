<?php

namespace App\Middleware;

class AuthMiddleware
{
    public static function handle(): bool
    {
        if (!\App\Helpers\Auth::check()) {
            $_SESSION['_redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /login', true, 302);
            echo view('auth.login');
            return false;
        }
        return true;
    }

    public static function guest(): bool
    {
        if (\App\Helpers\Auth::check()) {
            header('Location: /dashboard', true, 302);
            return false;
        }
        return true;
    }

    public static function role(string $role): callable
    {
        return function () use ($role) {
            if (!\App\Helpers\Auth::check()) {
                header('Location: /login', true, 302);
                return false;
            }
            if (!\App\Helpers\Auth::hasRole($role)) {
                http_response_code(403);
                echo view('errors.403');
                return false;
            }
            return true;
        };
    }

    public static function anyRole(array $roles): callable
    {
        return function () use ($roles) {
            if (!\App\Helpers\Auth::check()) {
                header('Location: /login', true, 302);
                return false;
            }
            if (!\App\Helpers\Auth::hasAnyRole($roles)) {
                http_response_code(403);
                echo view('errors.403');
                return false;
            }
            return true;
        };
    }
}
