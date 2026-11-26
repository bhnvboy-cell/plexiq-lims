<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;

class LabBatchItem extends BaseModel
{
    protected static string $table = 'lab_batch_items';
    protected static string $primaryKey = 'id';
}
