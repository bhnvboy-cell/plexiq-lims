<?php

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        static $config = [];
        if (empty($config)) {
            $configPath = __DIR__ . '/../../config';
            foreach (glob($configPath . '/*.php') as $file) {
                $name = basename($file, '.php');
                $config[$name] = require $file;
            }
        }
        $keys = explode('.', $key);
        $value = $config;
        foreach ($keys as $k) {
            if (!isset($value[$k])) return $default;
            $value = $value[$k];
        }
        return $value;
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        // Check getenv first (set by index.php via putenv)
        $val = getenv($key);
        if ($val !== false) return $val;
        // Check $_ENV
        if (isset($_ENV[$key])) return $_ENV[$key];
        // Fallback: read .env file and cache
        static $env = null;
        if ($env === null) {
            $envFile = __DIR__ . '/../../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), '#')) continue;
                    if (str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $env[trim($k)] = trim($v);
                    }
                }
            }
        }
        return $env[$key] ?? $default;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = __DIR__ . '/../../storage';
        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('layout')) {
    function layout(string $name): void
    {
        global $_lims_layout;
        $_lims_layout = $name;
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): string
    {
        global $_lims_layout;
        $_lims_layout = null;

        extract($data);
        $viewPath = __DIR__ . '/../../resources/views/' . str_replace('.', '/', $view) . '.php';
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$viewPath}");
        }
        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($_lims_layout) {
            $layoutPath = __DIR__ . '/../../resources/views/layouts/' . $_lims_layout . '.php';
            if (file_exists($layoutPath)) {
                ob_start();
                require $layoutPath;
                $content = ob_get_clean();
            }
        }

        return $content;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = '')
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(): bool
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected = $_SESSION['_csrf_token'] ?? '';
        if (empty($token) || empty($expected) || !hash_equals($expected, $token)) {
            return false;
        }
        // Rotate token after use
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        return true;
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('getallheaders')) {
    function getallheaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($name, 5));
                $headers[$headerName] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        if (isset($_SERVER['CONTENT_LENGTH'])) $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        if (isset($_SERVER['AUTHORIZATION'])) $headers['Authorization'] = $_SERVER['AUTHORIZATION'];
        return $headers;
    }
}

if (!function_exists('session_flash')) {
    function session_flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        static $translations = [];
        static $currentLang = null;

        if ($locale === null) {
            $locale = $_SESSION['_lang'] ?? 'en';
        }
        if ($currentLang !== $locale) {
            $currentLang = $locale;
            $db = \App\Helpers\Database::connect();
            $stmt = $db->prepare("
                SELECT t.translation_key, t.translation_value
                FROM translations t
                JOIN languages l ON t.language_id = l.id
                WHERE l.language_code = ? AND l.is_active = TRUE
            ");
            $stmt->execute([$locale]);
            $translations = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $translations[$row['translation_key']] = $row['translation_value'];
            }
        }

        $text = $translations[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $text = str_replace("{{$k}}", $v, $text);
        }
        return $text;
    }
}

if (!function_exists('notify')) {
    function notify(int $userId, string $type, string $title, string $message, ?string $link = null): void
    {
        try {
            $db = \App\Helpers\Database::connect();
            $stmt = $db->prepare("INSERT INTO notifications (user_id, notification_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $type, $title, $message, $link]);
        } catch (\Exception $e) {
            error_log("Notification error: " . $e->getMessage());
        }
    }
}

if (!function_exists('notify_role')) {
    function notify_role(string $roleName, string $type, string $title, string $message, ?string $link = null): void
    {
        try {
            $db = \App\Helpers\Database::connect();
            $users = $db->prepare("
                SELECT u.id FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE r.name = ? AND u.is_active = TRUE
            ");
            $users->execute([$roleName]);
            foreach ($users->fetchAll(\PDO::FETCH_ASSOC) as $user) {
                notify($user['id'], $type, $title, $message, $link);
            }
        } catch (\Exception $e) {
            error_log("notify_role error: " . $e->getMessage());
        }
    }
}

if (!function_exists('deployment_config')) {
    function deployment_config(string $key, $default = null)
    {
        static $settings = null;
        if ($settings === null) {
            try {
                $db = \App\Helpers\Database::connect();
                $stmt = $db->query("SELECT setting_key, setting_value FROM deployment_settings");
                $settings = [];
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (\Exception $e) {
                $settings = [];
            }
        }
        return $settings[$key] ?? $default;
    }
}

if (!function_exists('format_money')) {
    function format_money($amount, string $currency = 'USD'): string
    {
        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹'];
        $symbol = $symbols[$currency] ?? '$';
        return $symbol . number_format((float)$amount, 2);
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $map = [
            'Registered' => 'secondary',
            'Pending' => 'secondary',
            'In Progress' => 'info',
            'Completed' => 'success',
            'Reviewed' => 'primary',
            'Approved' => 'success',
            'Rejected' => 'danger',
            'COA Released' => 'success',
        ];
        $class = $map[$status] ?? 'secondary';
        return '<span class="badge bg-' . $class . '">' . htmlspecialchars($status) . '</span>';
    }
}
