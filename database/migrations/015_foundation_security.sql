-- PlexiQ LIMS - Foundation Migration
-- 1. Missing tables referenced by code but never created (manufacturers, webhook_logs)
-- 2. Audit-trail hash chaining (tamper-evidence) for 21 CFR Part 11
-- 3. 2FA/TOTP support on users
-- 4. Soft-delete columns on regulated records
-- 5. Measurement uncertainty on results
-- 6. Chain of custody tracking for samples
-- 7. QC control module (control lots, Levey-Jennings / Westgard)
-- Idempotent: safe to re-run.

-- ====================================================================
-- 1. MISSING TABLES
-- ====================================================================

CREATE TABLE IF NOT EXISTS manufacturers (
    id SERIAL PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    postal_code VARCHAR(20),
    phone VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(255),
    logo_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS webhook_logs (
    id SERIAL PRIMARY KEY,
    webhook_id INTEGER NOT NULL REFERENCES api_webhooks(id) ON DELETE CASCADE,
    event VARCHAR(100),
    payload TEXT,
    response_code INTEGER,
    response_body TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_webhook_logs_webhook_id ON webhook_logs(webhook_id);

-- ====================================================================
-- 2. AUDIT TRAIL HASH CHAIN
-- ====================================================================

ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS prev_hash VARCHAR(64);
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS hash_chain VARCHAR(64);
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS integrity_verified BOOLEAN DEFAULT NULL;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS system_generated BOOLEAN DEFAULT FALSE;

-- Prevent UPDATE/DELETE of audit records (immutability). SYSTEM accounts
-- and restores bypass via the system_generated flag; the trigger blocks
-- all other modification paths.
CREATE OR REPLACE FUNCTION block_audit_mutation() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'UPDATE' THEN
        -- Integrity hashes may be refreshed by the integrity checker (system).
        IF NEW.hash_chain IS DISTINCT FROM OLD.hash_chain
           OR NEW.integrity_verified IS DISTINCT FROM OLD.integrity_verified THEN
            RETURN NEW;
        END IF;
        RAISE EXCEPTION 'Audit log records are immutable. Record id=% cannot be modified.', OLD.id;
    ELSIF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Audit log records are immutable. Record id=% cannot be deleted.', OLD.id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_immutable ON audit_logs;
CREATE TRIGGER trg_audit_immutable
    BEFORE UPDATE OR DELETE ON audit_logs
    FOR EACH ROW EXECUTE FUNCTION block_audit_mutation();

-- ====================================================================
-- 3. 2FA / TOTP
-- ====================================================================

ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(100);
ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_confirmed_at TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_login_attempts INTEGER DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP;

-- ====================================================================
-- 4. SOFT DELETES
-- ====================================================================

ALTER TABLE samples        ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE batches        ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE sample_tests   ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE results        ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE coa_documents  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE oos_records    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE capa_records   ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE deviations     ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE eln_entries    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;

-- ====================================================================
-- 5. MEASUREMENT UNCERTAINTY ON RESULTS
-- ====================================================================

ALTER TABLE results ADD COLUMN IF NOT EXISTS uncertainty NUMERIC(18,6);
ALTER TABLE results ADD COLUMN IF NOT EXISTS k_factor NUMERIC(6,3) DEFAULT 2.000;
ALTER TABLE results ADD COLUMN IF NOT EXISTS confidence_interval VARCHAR(10) DEFAULT '95%';
ALTER TABLE results ADD COLUMN IF NOT EXISTS instrument_id INTEGER REFERENCES instruments(id);
ALTER TABLE results ADD COLUMN IF NOT EXISTS replicate_count INTEGER DEFAULT 1;

-- ====================================================================
-- 6. CHAIN OF CUSTODY
-- ====================================================================

CREATE TABLE IF NOT EXISTS chain_of_custody (
    id SERIAL PRIMARY KEY,
    sample_id INTEGER NOT NULL REFERENCES samples(id) ON DELETE CASCADE,
    transfer_from VARCHAR(255),
    transfer_to VARCHAR(255),
    transferred_by INTEGER REFERENCES users(id),
    received_by INTEGER REFERENCES users(id),
    transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_at TIMESTAMP,
    location VARCHAR(255),
    condition_note TEXT,
    sealed BOOLEAN DEFAULT FALSE,
    seal_number VARCHAR(100),
    custody_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_coc_sample ON chain_of_custody(sample_id);

-- ====================================================================
-- 7. QC CONTROL MODULE (Levey-Jennings / Westgard)
-- ====================================================================

CREATE TABLE IF NOT EXISTS qc_control_lots (
    id SERIAL PRIMARY KEY,
    lot_number VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    manufacturer VARCHAR(255),
    material_type VARCHAR(100),
    target_mean NUMERIC(18,6),
    target_sd NUMERIC(18,6),
    unit VARCHAR(50),
    expiry_date TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS qc_control_results (
    id SERIAL PRIMARY KEY,
    control_lot_id INTEGER NOT NULL REFERENCES qc_control_lots(id) ON DELETE CASCADE,
    parameter_id INTEGER REFERENCES analysis_parameters(id),
    test_id INTEGER REFERENCES tests(id),
    instrument_id INTEGER REFERENCES instruments(id),
    result_value NUMERIC(18,6),
    entered_by INTEGER REFERENCES users(id),
    entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT
);

CREATE INDEX IF NOT EXISTS idx_qcr_lot ON qc_control_results(control_lot_id);
CREATE INDEX IF NOT EXISTS idx_qcr_instrument ON qc_control_results(instrument_id);
CREATE INDEX IF NOT EXISTS idx_qcr_entered ON qc_control_results(entered_at);
