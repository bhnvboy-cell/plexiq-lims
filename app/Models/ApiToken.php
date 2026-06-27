<?php

namespace App\Models;

use App\BaseModel;

class ApiToken extends BaseModel
{
    protected static string $table = 'api_tokens';

    public static function generateToken(int $userId, string $name, array $permissions = []): ?array
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $data = [
            'user_id' => $userId,
            'name' => $name,
            'token_hash' => $hash,
            'permissions' => json_encode($permissions),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
        ];

        $result = static::create($data);
        if ($result) {
            $result['plain_token'] = $token;
        }
        return $result;
    }

    public static function validateToken(string $token): ?array
    {
        $hash = hash('sha256', $token);

        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT * FROM " . static::$table . "
            WHERE token_hash = ?
              AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$hash]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
