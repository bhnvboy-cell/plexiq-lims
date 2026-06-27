<?php

namespace App\Models;

use App\BaseModel;

class Project extends BaseModel
{
    protected static string $table = 'projects';
    protected static string $primaryKey = 'id';

    public static function withSamples(int $id): ?array
    {
        $db = \App\Helpers\Database::connect();
        $project = self::find($id);
        if (!$project) return null;
        $samples = $db->prepare("
            SELECT s.*, ps.notes FROM project_samples ps
            JOIN samples s ON ps.sample_id = s.id
            WHERE ps.project_id = ?
        ");
        $samples->execute([$id]);
        $project['samples'] = $samples->fetchAll();
        return $project;
    }
}
