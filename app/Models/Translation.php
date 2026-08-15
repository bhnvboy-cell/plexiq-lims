<?php

namespace App\Models;

use App\BaseModel;

class Translation extends BaseModel
{
    protected static string $table = 'translations';

    public static function getByLanguage(int $languageId): array
    {
        return static::where('language_id', $languageId);
    }

    public static function translate(string $key, int $languageId): ?string
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT translation_value FROM " . static::$table . "
            WHERE translation_key = ? AND language_id = ?
            LIMIT 1
        ");
        $stmt->execute([$key, $languageId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['translation_value'] : null;
    }
}
