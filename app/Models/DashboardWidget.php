<?php

namespace App\Models;

use App\BaseModel;

class DashboardWidget extends BaseModel
{
    protected static string $table = 'dashboard_widgets';

    public static function getUserWidgets(int $userId): array
    {
        return static::where('user_id', $userId);
    }
}
