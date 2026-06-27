-- Migration 005: Installer Build Records
CREATE TABLE IF NOT EXISTS installer_builds (
    id SERIAL PRIMARY KEY,
    build_id VARCHAR(100) NOT NULL UNIQUE,
    app_name VARCHAR(200) DEFAULT 'PlexiQ LIMS Server',
    app_version VARCHAR(20) DEFAULT '2.0',
    server_port INTEGER DEFAULT 8080,
    db_host VARCHAR(100) DEFAULT '127.0.0.1',
    db_name VARCHAR(100) DEFAULT 'limsdb',
    exit_code INTEGER,
    exe_size BIGINT DEFAULT 0,
    build_time DECIMAL(10,2),
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
