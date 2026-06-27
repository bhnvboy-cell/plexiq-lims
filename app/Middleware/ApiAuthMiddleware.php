<?php

namespace App\Middleware;

use App\Helpers\Database;

class ApiAuthMiddleware
{
    public static function authenticate(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Missing or invalid Authorization header. Use: Bearer <token>']);
            return false;
        }

        $token = substr($authHeader, 7);
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT at.*, u.username, u.role_id, r.name AS role_name
            FROM api_tokens at
            JOIN users u ON at.user_id = u.id
            JOIN roles r ON u.role_id = r.id
            WHERE at.token_hash = ?
              AND at.is_active = TRUE
              AND (at.expires_at IS NULL OR at.expires_at > CURRENT_TIMESTAMP)
        ");
        $stmt->execute([hash('sha256', $token)]);
        $tokenData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tokenData) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid or expired token.']);
            return false;
        }

        // Update last used
        $db->prepare("UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$tokenData['id']]);

        // Store token info for controllers
        $_REQUEST['_api_user_id'] = $tokenData['user_id'];
        $_REQUEST['_api_role'] = $tokenData['role_name'];
        $_REQUEST['_api_token_id'] = $tokenData['id'];
        $_REQUEST['_api_permissions'] = json_decode($tokenData['permissions'] ?? '[]', true);

        return true;
    }
}
