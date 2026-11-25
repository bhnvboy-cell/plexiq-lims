-- ====================================================================
-- Migration: 024_scale_multisite_production_ops.sql
-- Description: Phase 7 Scale, Multi-Site & Production Operations
--   1. Multi-Site Facility Management & Tenant Isolation
--   2. High-Performance Job Queue & Dead-Letter Queue (DLQ)
--   3. Cold Data Archival with SHA-256 Tamper Sealing
-- ====================================================================

-- 1. MULTI-SITE / FACILITIES
CREATE TABLE IF NOT EXISTS sites (
    id SERIAL PRIMARY KEY,
    site_code VARCHAR(30) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    facility_type VARCHAR(50) DEFAULT 'Testing_Laboratory' CHECK (facility_type IN ('Testing_Laboratory', 'Manufacturing_Plant', 'R_and_D_Center', 'Distribution_Depot')),
    country_code VARCHAR(3) NOT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    regulatory_regime VARCHAR(100) DEFAULT 'FDA_21CFR_Part211_ISO17025',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default global sites
INSERT INTO sites (site_code, name, facility_type, country_code, timezone, regulatory_regime)
VALUES 
    ('SITE-BOS-01', 'Boston Central QC Testing Facility', 'Testing_Laboratory', 'USA', 'America/New_York', 'FDA_21CFR_Part211_ISO17025'),
    ('SITE-DUB-02', 'Dublin Biologics & Vaccines QC Lab', 'Testing_Laboratory', 'IRL', 'Europe/Dublin', 'EU_Annex11_GMP'),
    ('SITE-SIN-03', 'Singapore High-Purity API Lab', 'Manufacturing_Plant', 'SGP', 'Asia/Singapore', 'HSA_GMP_ISO17025')
ON CONFLICT (site_code) DO NOTHING;

-- Associate site_id on core operational tables
ALTER TABLE users ADD COLUMN IF NOT EXISTS site_id INTEGER REFERENCES sites(id);
ALTER TABLE samples ADD COLUMN IF NOT EXISTS site_id INTEGER REFERENCES sites(id);
ALTER TABLE instruments ADD COLUMN IF NOT EXISTS site_id INTEGER REFERENCES sites(id);

CREATE INDEX IF NOT EXISTS idx_samples_site ON samples(site_id);
CREATE INDEX IF NOT EXISTS idx_users_site ON users(site_id);
CREATE INDEX IF NOT EXISTS idx_instruments_site ON instruments(site_id);

-- Update default user and samples to primary site if null
UPDATE users SET site_id = (SELECT id FROM sites WHERE site_code = 'SITE-BOS-01' LIMIT 1) WHERE site_id IS NULL;
UPDATE samples SET site_id = (SELECT id FROM sites WHERE site_code = 'SITE-BOS-01' LIMIT 1) WHERE site_id IS NULL;
UPDATE instruments SET site_id = (SELECT id FROM sites WHERE site_code = 'SITE-BOS-01' LIMIT 1) WHERE site_id IS NULL;

-- 2. JOB QUEUES & DEAD LETTER QUEUE (Redis / DB Failover Engine)
CREATE TABLE IF NOT EXISTS job_queues (
    id SERIAL PRIMARY KEY,
    queue_name VARCHAR(50) NOT NULL DEFAULT 'default',
    job_class VARCHAR(255) NOT NULL,
    payload JSONB NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 3,
    status VARCHAR(30) DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'dead_letter')),
    error_message TEXT,
    reserved_at TIMESTAMP,
    available_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_job_queues_status ON job_queues(queue_name, status, available_at);

-- 3. REGULATORY ARCHIVAL & DATA RETENTION TABLE (ALCOA+ Sealed)
CREATE TABLE IF NOT EXISTS archive_records (
    id SERIAL PRIMARY KEY,
    archive_batch_code VARCHAR(100) UNIQUE NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    record_count INTEGER NOT NULL,
    date_range_start TIMESTAMP NOT NULL,
    date_range_end TIMESTAMP NOT NULL,
    sha256_checksum VARCHAR(64) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    archived_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_archive_batch ON archive_records(archive_batch_code);
