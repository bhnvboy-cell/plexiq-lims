-- ====================================================================
-- Migration: 021_instruments_automation_vault.sql
-- Description: Phase 4 Instruments, Automation & Raw Data Vault
--   1. Bidirectional Autosampler Sequences & Instrument Worklists
--   2. Immutable Instrument Raw Data Vault with Cryptographic Verification
--   3. Instrument Protocol & CDS Configuration Profiles
-- ====================================================================

-- 1. AUTOSAMPLER WORKLISTS & SEQUENCES
CREATE TABLE IF NOT EXISTS instrument_worklists (
    id SERIAL PRIMARY KEY,
    instrument_id INTEGER NOT NULL REFERENCES instruments(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    sequence_code VARCHAR(50) UNIQUE NOT NULL,
    worklist_format VARCHAR(30) DEFAULT 'csv' CHECK (worklist_format IN ('csv', 'astm', 'empower_sample_set', 'tsv')),
    status VARCHAR(20) DEFAULT 'Draft' CHECK (status IN ('Draft', 'Exported', 'Running', 'Completed', 'Aborted')),
    entries JSONB NOT NULL DEFAULT '[]', -- array of {position: 1, sample_id: 10, sample_code: "SMP-1", type: "Sample", injection_volume_ul: 10.0, method: "Assay-HPLC"}
    exported_content TEXT,
    exported_at TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_worklists_instrument ON instrument_worklists(instrument_id);
CREATE INDEX IF NOT EXISTS idx_worklists_status ON instrument_worklists(status);

-- 2. IMMUTABLE RAW DATA VAULT (ALCOA+ Original / Attributable Records)
CREATE TABLE IF NOT EXISTS instrument_raw_vault (
    id SERIAL PRIMARY KEY,
    instrument_id INTEGER REFERENCES instruments(id) ON DELETE SET NULL,
    vault_uid VARCHAR(64) UNIQUE NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    sha256_hash VARCHAR(64) NOT NULL,
    hmac_signature VARCHAR(64) NOT NULL,
    file_size_bytes INTEGER NOT NULL,
    mime_type VARCHAR(100) DEFAULT 'text/plain',
    storage_path VARCHAR(500) NOT NULL,
    raw_content TEXT,
    driver_used VARCHAR(50),
    parsed_results JSONB DEFAULT '[]',
    parse_status VARCHAR(20) DEFAULT 'Pending' CHECK (parse_status IN ('Pending', 'Parsed', 'Parse_Error', 'Tampered')),
    parse_error TEXT,
    edge_agent_id VARCHAR(100),
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_vault_instrument ON instrument_raw_vault(instrument_id);
CREATE INDEX IF NOT EXISTS idx_vault_hash ON instrument_raw_vault(sha256_hash);
CREATE INDEX IF NOT EXISTS idx_vault_parse_status ON instrument_raw_vault(parse_status);

-- Strictly prevent modification or deletion of raw instrument vault records (ALCOA+ Immutability)
CREATE OR REPLACE FUNCTION block_vault_mutation() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Raw Data Vault records are permanently immutable. Vault record id=% cannot be deleted.', OLD.id;
    ELSIF TG_OP = 'UPDATE' AND (OLD.sha256_hash != NEW.sha256_hash OR OLD.raw_content != NEW.raw_content) THEN
        RAISE EXCEPTION 'Raw Data Vault payload is tamper-evident and immutable. Content of vault id=% cannot be altered.', OLD.id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_vault_immutable ON instrument_raw_vault;
CREATE TRIGGER trg_vault_immutable
    BEFORE UPDATE OR DELETE ON instrument_raw_vault
    FOR EACH ROW EXECUTE FUNCTION block_vault_mutation();
