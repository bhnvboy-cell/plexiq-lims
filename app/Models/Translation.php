<?php

namespace App\Models;

use App\BaseModel;

class Translation extends BaseModel
{
    protected static string $table = 'translations';

    public static function getByLanguage(int|string $language): array
    {
        if (is_numeric($language)) {
            return static::where('language_id', (int)$language);
        }
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT t.*, l.language_code
            FROM " . static::$table . " t
            JOIN languages l ON t.language_id = l.id
            WHERE l.language_code = ?
            ORDER BY t.translation_key ASC
        ");
        $stmt->execute([$language]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function translate(string $key, int|string $language = 'en'): ?string
    {
        $db = \App\Helpers\Database::connect();
        if (is_numeric($language)) {
            $stmt = $db->prepare("
                SELECT translation_value FROM " . static::$table . "
                WHERE translation_key = ? AND language_id = ?
                LIMIT 1
            ");
            $stmt->execute([$key, (int)$language]);
        } else {
            $stmt = $db->prepare("
                SELECT t.translation_value
                FROM " . static::$table . " t
                JOIN languages l ON t.language_id = l.id
                WHERE t.translation_key = ? AND l.language_code = ?
                LIMIT 1
            ");
            $stmt->execute([$key, $language]);
        }
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['translation_value'] : null;
    }
}
