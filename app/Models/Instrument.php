<?php

namespace App\Models;

use App\BaseModel;

class Instrument extends BaseModel
{
    protected static string $table = 'instruments';
    protected static string $primaryKey = 'id';
}
