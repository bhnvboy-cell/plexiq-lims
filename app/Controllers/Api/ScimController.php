<?php

namespace App\Controllers\Api;

use App\BaseController;
use App\Helpers\Database;
use App\Helpers\Audit;

class ScimController extends BaseController
{
    private function authenticateScim(): bool
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (!str_starts_with($auth, 'Bearer ')) {
            return false;
        }
        $token = substr($auth, 7);
        $db = Database::connect();
        $stmt = $db->prepare("SELECT id FROM api_tokens WHERE token_hash = ? AND is_active = TRUE AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)");
        $stmt->execute([hash('sha256', $token)]);
        return (bool)$stmt->fetchColumn();
    }

    public function listUsers(): string
    {
        if (!$this->authenticateScim()) {
            return $this->scimError('Unauthorized', 401);
        }

        $db = Database::connect();
        $stmt = $db->query("SELECT u.id, u.username, u.email, u.full_name, u.is_active, u.created_at, u.updated_at, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC");
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $resources = [];
        foreach ($users as $u) {
            $resources[] = $this->formatScimUser($u);
        }

        return $this->scimResponse([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => count($resources),
            'startIndex' => 1,
            'itemsPerPage' => count($resources),
            'Resources' => $resources,
        ]);
    }

    public function getUser(int $id): string
    {
        if (!$this->authenticateScim()) {
            return $this->scimError('Unauthorized', 401);
        }

        $db = Database::connect();
        $stmt = $db->prepare("SELECT u.id, u.username, u.email, u.full_name, u.is_active, u.created_at, u.updated_at, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            return $this->scimError('User not found', 404);
        }

        return $this->scimResponse($this->formatScimUser($user));
    }

    public function createUser(): string
    {
        if (!$this->authenticateScim()) {
            return $this->scimError('Unauthorized', 401);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            return $this->scimError('Invalid SCIM JSON payload', 400);
        }

        $username = $input['userName'] ?? '';
        $email = $input['emails'][0]['value'] ?? ($input['email'] ?? '');
        $displayName = $input['displayName'] ?? ($input['name']['formatted'] ?? $username);
        $active = isset($input['active']) ? (bool)$input['active'] : true;

        if (empty($username) || empty($email)) {
            return $this->scimError('userName and email are required', 400);
        }

        $db = Database::connect();
        $defaultPassword = bin2hex(random_bytes(8)) . '!Aa1';
        $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

        // Map role
        $roleName = $input['roles'][0]['value'] ?? 'Analyst';
        $roleStmt = $db->prepare("SELECT id FROM roles WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $roleStmt->execute([$roleName]);
        $roleId = (int)($roleStmt->fetchColumn() ?: 2); // Default to Analyst

        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role_id, is_active, must_change_password)
            VALUES (?, ?, ?, ?, ?, ?, TRUE)
            RETURNING id, username, email, full_name, is_active, created_at, updated_at
        ");
        $stmt->execute([$username, $email, $hash, $displayName, $roleId, $active ? 't' : 'f']);
        $created = $stmt->fetch(\PDO::FETCH_ASSOC);
        $created['role_name'] = $roleName;

        Audit::log('SCIM User Provisioned', 'users', (int)$created['id'], null, ['username' => $username, 'email' => $email]);

        return $this->scimResponse($this->formatScimUser($created), 201);
    }

    public function deleteUser(int $id): string
    {
        if (!$this->authenticateScim()) {
            return $this->scimError('Unauthorized', 401);
        }

        $db = Database::connect();
        $db->prepare("UPDATE users SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        Audit::log('SCIM User Deactivated', 'users', $id);

        if (!headers_sent()) {
            http_response_code(204);
        }
        return '';
    }

    public function listGroups(): string
    {
        if (!$this->authenticateScim()) {
            return $this->scimError('Unauthorized', 401);
        }

        $db = Database::connect();
        $roles = $db->query("SELECT id, name, description FROM roles ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);
        $groups = [];
        foreach ($roles as $r) {
            $groups[] = [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
                'id' => (string)$r['id'],
                'displayName' => $r['name'],
                'description' => $r['description'],
                'meta' => [
                    'resourceType' => 'Group',
                    'location' => '/api/scim/v2/Groups/' . $r['id']
                ]
            ];
        }

        return $this->scimResponse([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => count($groups),
            'Resources' => $groups
        ]);
    }

    private function formatScimUser(array $u): array
    {
        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'id' => (string)$u['id'],
            'userName' => $u['username'],
            'name' => [
                'formatted' => $u['full_name'],
            ],
            'displayName' => $u['full_name'],
            'emails' => [
                ['value' => $u['email'], 'primary' => true]
            ],
            'roles' => [
                ['value' => $u['role_name'] ?? 'Analyst']
            ],
            'active' => (bool)$u['is_active'],
            'meta' => [
                'resourceType' => 'User',
                'created' => isset($u['created_at']) ? date('c', strtotime($u['created_at'])) : date('c'),
                'lastModified' => isset($u['updated_at']) ? date('c', strtotime($u['updated_at'])) : date('c'),
                'location' => '/api/scim/v2/Users/' . $u['id']
            ]
        ];
    }

    private function scimResponse(array $data, int $status = 200): string
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/scim+json; charset=utf-8');
        }
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function scimError(string $message, int $status): string
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/scim+json; charset=utf-8');
        }
        return json_encode([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
            'status' => (string)$status,
            'detail' => $message,
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
