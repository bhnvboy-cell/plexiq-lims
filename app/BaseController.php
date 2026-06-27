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
        header('Content-Type: application/json');
        return json_encode($data);
    }

    protected function redirect(string $url): void
    {
        redirect($url);
    }

    protected function back(): void
    {
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleSet) {
            $ruleList = explode('|', $ruleSet);
            foreach ($ruleList as $rule) {
                if ($rule === 'required' && empty($data[$field])) {
                    $errors[$field][] = "The {$field} field is required.";
                }
                if (str_starts_with($rule, 'min:')) {
                    $min = explode(':', $rule)[1];
                    if (strlen($data[$field] ?? '') < $min) {
                        $errors[$field][] = "The {$field} must be at least {$min} characters.";
                    }
                }
                if (str_starts_with($rule, 'max:')) {
                    $max = explode(':', $rule)[1];
                    if (strlen($data[$field] ?? '') > $max) {
                        $errors[$field][] = "The {$field} must not exceed {$max} characters.";
                    }
                }
                if ($rule === 'email' && !filter_var($data[$field] ?? '', FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} must be a valid email address.";
                }
                if ($rule === 'numeric' && !is_numeric($data[$field] ?? '')) {
                    $errors[$field][] = "The {$field} must be a number.";
                }
                if (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', explode(':', $rule, 2)[1]);
                    if (!in_array($data[$field] ?? '', $allowed)) {
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
