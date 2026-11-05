<?php

namespace App\Models;

use App\BaseModel;

class DeploymentSetting extends BaseModel
{
    protected static string $table = 'deployment_settings';

    public static function get(string $key, $default = null): ?string
    {
        $result = static::where('setting_key', $key);
        return !empty($result) ? $result[0]['setting_value'] : $default;
    }

    public static function set(string $key, $value): bool
    {
        $existing = static::where('setting_key', $key);
        if (!empty($existing)) {
            return static::update((int)$existing[0]['id'], ['setting_value' => $value]);
        }
        return (bool)static::create(['setting_key' => $key, 'setting_value' => $value]);
    }
}
