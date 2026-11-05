-- ============================================================
-- Migration 017: Fix Schema Gaps & Column Mismatches
-- Idempotent: safe to re-run
-- ============================================================

-- 1. Deviations & Deviation Actions
ALTER TABLE deviations ADD COLUMN IF NOT EXISTS closed_by INTEGER REFERENCES users(id);
ALTER TABLE deviations ADD COLUMN IF NOT EXISTS gmp_impact BOOLEAN DEFAULT FALSE;
ALTER TABLE deviations ADD COLUMN IF NOT EXISTS batch_disposition VARCHAR(100);

ALTER TABLE deviation_actions ADD COLUMN IF NOT EXISTS completed_by INTEGER REFERENCES users(id);

-- 2. Training Assignments
ALTER TABLE training_assignments ADD COLUMN IF NOT EXISTS passed BOOLEAN DEFAULT TRUE;
ALTER TABLE training_assignments ADD COLUMN IF NOT EXISTS completed_by INTEGER REFERENCES users(id);
ALTER TABLE training_assignments ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP;

-- 3. SAP Sync Logs & SAP Config
ALTER TABLE sap_sync_logs ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE sap_config ALTER COLUMN config_value DROP NOT NULL;
UPDATE sap_config SET config_value = NULL WHERE config_key = 'sap_last_sync_at' AND config_value = '';

-- 4. Electronic Signatures (21 CFR Part 11)
ALTER TABLE electronic_signatures ADD COLUMN IF NOT EXISTS signature_type VARCHAR(100);
ALTER TABLE electronic_signatures ADD COLUMN IF NOT EXISTS signature_reason TEXT;
ALTER TABLE electronic_signatures ADD COLUMN IF NOT EXISTS user_agent TEXT;
ALTER TABLE electronic_signatures ADD COLUMN IF NOT EXISTS content_hash VARCHAR(64);

-- 5. COA Documents (Amendments & PDF Hash)
ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS amendment_no INTEGER DEFAULT 0;
ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS amendment_reason TEXT;
ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS supersedes_id INTEGER;
ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS pdf_hash VARCHAR(64);

-- 6. OOS Records (Phase I / Phase II & CAPA Link)
ALTER TABLE oos_records ADD COLUMN IF NOT EXISTS phase VARCHAR(30) DEFAULT 'Phase I';
ALTER TABLE oos_records ADD COLUMN IF NOT EXISTS capa_id INTEGER;

-- 7. ELN Entries & BI Reports & Calibrations & API Rate Limits
ALTER TABLE eln_entries ADD COLUMN IF NOT EXISTS project_id INTEGER REFERENCES projects(id);
ALTER TABLE dashboard_reports ADD COLUMN IF NOT EXISTS query_sql TEXT;
ALTER TABLE dashboard_reports ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE dashboard_reports ADD COLUMN IF NOT EXISTS connection_id INTEGER REFERENCES bi_connections(id);
ALTER TABLE calibration_records ADD COLUMN IF NOT EXISTS next_due_date DATE;
ALTER TABLE calibration_records ADD COLUMN IF NOT EXISTS completed_date DATE;
ALTER TABLE environmental_readings ADD COLUMN IF NOT EXISTS is_alert BOOLEAN DEFAULT FALSE;
ALTER TABLE environmental_readings ADD COLUMN IF NOT EXISTS recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE api_rate_limits ADD COLUMN IF NOT EXISTS endpoint VARCHAR(255) DEFAULT '*';
ALTER TABLE api_rate_limits ADD COLUMN IF NOT EXISTS window_end TIMESTAMP;

-- 8. Ensure webhook_logs composite index exists now that 015 has created webhook_logs
DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'webhook_logs') THEN
        CREATE INDEX IF NOT EXISTS idx_webhook_logs_webhook_created ON webhook_logs(webhook_id, created_at);
    END IF;
END $$;
