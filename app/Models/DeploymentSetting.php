<?php

namespace App\Models;

use App\BaseModel;

class DeploymentSetting extends BaseModel
{
    protected static string $table = 'deployment_settings';

    public static function get(string $key, $default = null): ?string
    {
        $result = static::where('key', $key);
        return !empty($result) ? $result[0]['value'] : $default;
    }

    public static function set(string $key, $value): bool
    {
        $existing = static::where('key', $key);
        if (!empty($existing)) {
            return static::update((int)$existing[0]['id'], ['value' => $value]);
        }
        return (bool)static::create(['key' => $key, 'value' => $value]);
    }
}
