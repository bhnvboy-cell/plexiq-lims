-- PlexiQ LIMS - Backup & Restore module
-- Creates settings + run-log tables for the backup engine.
-- Idempotent: safe to re-run.

CREATE TABLE IF NOT EXISTS backup_settings (
    setting_key   TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL DEFAULT '',
    updated_at    TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO backup_settings (setting_key, setting_value) VALUES
    ('retention_count', '10'),
    ('pg_dump_path', ''),
    ('psql_path', '')
ON CONFLICT (setting_key) DO NOTHING;

CREATE TABLE IF NOT EXISTS backup_runs (
    id           BIGSERIAL PRIMARY KEY,
    backup_type  TEXT NOT NULL DEFAULT 'manual',
    file_name    TEXT,
    file_size    BIGINT NOT NULL DEFAULT 0,
    status       TEXT NOT NULL DEFAULT 'success',
    message      TEXT,
    duration_ms  INTEGER,
    triggered_by INTEGER REFERENCES users(id),
    created_at   TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_backup_runs_created_at ON backup_runs (created_at DESC);
