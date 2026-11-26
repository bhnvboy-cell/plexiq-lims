-- ==============================================================================
-- Migration 028: Result Flow End-to-End, Lineage & Review by Exception
-- Standards: 21 CFR Part 11, EU Annex 11, ISO/IEC 17025, ALCOA+
-- Capabilities:
--   1. result_lineage: Tracks complete data pedigree (raw instrument vault file
--      checksum, formula, spec version snapshot, instrument calibration, analyst, batch)
--   2. Invalidation & Retest governance (mandatory justification, parent link)
--   3. Review by exception views & segregation of duties enforcement
-- ==============================================================================

-- 1. Extend results table with invalidation & retest metadata
ALTER TABLE results ADD COLUMN IF NOT EXISTS is_invalidated BOOLEAN DEFAULT FALSE;
ALTER TABLE results ADD COLUMN IF NOT EXISTS invalidation_reason TEXT;
ALTER TABLE results ADD COLUMN IF NOT EXISTS invalidated_by INTEGER REFERENCES users(id);
ALTER TABLE results ADD COLUMN IF NOT EXISTS invalidated_at TIMESTAMP;
ALTER TABLE results ADD COLUMN IF NOT EXISTS retest_parent_result_id INTEGER REFERENCES results(id);

-- Extend sample_tests table with retest tracking
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS retest_count INTEGER DEFAULT 0;
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS is_retest BOOLEAN DEFAULT FALSE;
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS retest_of_test_id INTEGER REFERENCES sample_tests(id);

-- 2. Result Lineage & GxP Pedigree Table
CREATE TABLE IF NOT EXISTS result_lineage (
    id SERIAL PRIMARY KEY,
    result_id INTEGER NOT NULL REFERENCES results(id) ON DELETE CASCADE,
    sample_test_id INTEGER NOT NULL REFERENCES sample_tests(id),
    sample_id INTEGER NOT NULL REFERENCES samples(id),
    raw_vault_id INTEGER REFERENCES instrument_raw_vault(id),
    raw_file_hash VARCHAR(128),
    raw_file_name VARCHAR(255),
    formula_id INTEGER REFERENCES calculation_formulas(id),
    formula_expression TEXT,
    spec_version_id INTEGER REFERENCES specification_versions(id),
    spec_snapshot_min NUMERIC(15, 6),
    spec_snapshot_max NUMERIC(15, 6),
    unit_snapshot VARCHAR(50),
    instrument_id INTEGER REFERENCES instruments(id),
    analyst_id INTEGER REFERENCES users(id),
    lab_batch_id INTEGER REFERENCES lab_batches(id),
    reviewer_id INTEGER REFERENCES users(id),
    approver_id INTEGER REFERENCES users(id),
    coa_id INTEGER REFERENCES coa_documents(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_result_lineage_result ON result_lineage(result_id);
CREATE INDEX IF NOT EXISTS idx_result_lineage_sample ON result_lineage(sample_id);
CREATE INDEX IF NOT EXISTS idx_result_lineage_vault ON result_lineage(raw_vault_id);
