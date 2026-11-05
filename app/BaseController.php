<?php

namespace App;

abstract class BaseController
{
    protected function render(string $view, array $data = []): string
    {
        $data['auth'] = [
            'check' => \App\Helpers\Auth::check(),
            'user' => \App\Helpers\Auth::user(),
            'role' => \App\Helpers\Auth::role(),
        ];
        $data['flash'] = [
            'success' => session_flash('success'),
            'error' => session_flash('error'),
            'warning' => session_flash('warning'),
            'info' => session_flash('info'),
        ];
        return view($view, $data);
    }

    protected function json($data, int $statusCode = 200): string
    {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        return json_encode($data);
    }

    protected function redirect(string $url): void
    {
        redirect($url);
    }

    protected function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $target = '/';
        if ($referer !== '') {
            if (str_starts_with($referer, '/') && !str_starts_with($referer, '//')) {
                $target = $referer;
            } else {
                $parsed = parse_url($referer);
                $currentHost = $_SERVER['HTTP_HOST'] ?? '';
                if (!empty($parsed['host']) && $currentHost !== '') {
                    $refHostPort = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
                    if (strcasecmp($parsed['host'], $currentHost) === 0 || strcasecmp($refHostPort, $currentHost) === 0) {
                        $target = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
                    }
                }
            }
        }
        redirect($target);
    }

    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleSet) {
            $ruleList = explode('|', $ruleSet);
            $val = $data[$field] ?? null;
            $isEmpty = ($val === null || $val === '' || $val === []);

            foreach ($ruleList as $rule) {
                if ($rule === 'required') {
                    if ($isEmpty) {
                        $errors[$field][] = "The {$field} field is required.";
                    }
                    continue;
                }

                // Skip non-required rules when value is empty
                if ($isEmpty) {
                    continue;
                }

                if (str_starts_with($rule, 'min:')) {
                    $min = (int)explode(':', $rule)[1];
                    if (strlen((string)$val) < $min) {
                        $errors[$field][] = "The {$field} must be at least {$min} characters.";
                    }
                }
                if (str_starts_with($rule, 'max:')) {
                    $max = (int)explode(':', $rule)[1];
                    if (strlen((string)$val) > $max) {
                        $errors[$field][] = "The {$field} must not exceed {$max} characters.";
                    }
                }
                if ($rule === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} must be a valid email address.";
                }
                if ($rule === 'numeric' && !is_numeric($val)) {
                    $errors[$field][] = "The {$field} must be a number.";
                }
                if (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', explode(':', $rule, 2)[1]);
                    if (!in_array((string)$val, $allowed, true)) {
                        $errors[$field][] = "The {$field} must be one of: " . implode(', ', $allowed);
                    }
                }
            }
        }
        return $errors;
    }

    protected function paginate(string $table, int $perPage = 20, array $where = [], array $params = []): array
    {
        $db = \App\Helpers\Database::connect();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $whereClause = '';
        if (!empty($where)) {
            $whereClause = 'WHERE ' . implode(' AND ', $where);
        }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM {$table} {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("SELECT * FROM {$table} {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
            'perPage' => $perPage,
            'currentPage' => $page,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        ];
    }
}
