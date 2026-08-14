<?php

return [
    // Directory where backups are stored (relative to storage/).
    'path' => 'backups',

    // Number of backups to retain. Older ones are pruned after each run.
    'retention_count' => (int)env('BACKUP_RETENTION', 10),

    // PostgreSQL binaries. Leave empty to auto-discover common install paths.
    'pg_dump_path' => env('PG_DUMP_PATH', ''),
    'psql_path' => env('PSQL_PATH', ''),

    // Dump flags passed to pg_dump.
    'dump_flags' => '-Fp --clean --if-exists --no-owner',
];
