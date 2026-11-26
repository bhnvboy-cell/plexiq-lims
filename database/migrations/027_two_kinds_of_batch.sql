-- ==============================================================================
-- Migration 027: Two Kinds of Batch, Kept Separate (Commercial LIMS Architecture)
-- Standards: 21 CFR Part 11, EU Annex 11, ISO/IEC 17025, ALCOA+
-- Concepts:
--   1. production_lots: Manufactured lot or raw shipment, product link, ERP batch,
--      lot disposition with Part 11 e-signature and ERP sync idempotency.
--   2. lab_batches: Analytical run / sequence / worklist (samples + controls: blank,
--      calibration standard, qc control, duplicate, spike), QC calculation,
--      auto-invalidation and test requeueing on failed batch QC.
--   3. Instrument worklist sequence generation (Empower, Chromeleon, OpenLab).
--   4. Backward-compatible view & backfill from legacy batches.
-- ==============================================================================

-- 1. Production Lots Table
CREATE TABLE IF NOT EXISTS production_lots (
    id SERIAL PRIMARY KEY,
    lot_number VARCHAR(100) UNIQUE NOT NULL,
    product_id INTEGER NOT NULL REFERENCES products(id),
    spec_version_id INTEGER REFERENCES specification_versions(id),
    erp_batch_number VARCHAR(100),
    site_id INTEGER REFERENCES sites(id),
    manufacturing_date DATE,
    expiry_date DATE,
    lot_size NUMERIC(15, 4),
    lot_unit VARCHAR(50) DEFAULT 'kg',
    disposition VARCHAR(50) DEFAULT 'Pending'
        CHECK (disposition IN ('Pending', 'Released', 'On Hold', 'Rejected', 'Conditionally Released')),
    disposition_by INTEGER REFERENCES users(id),
    disposition_at TIMESTAMP,
    disposition_reason TEXT,
    disposition_signature_hash VARCHAR(128),
    erp_sync_status VARCHAR(50) DEFAULT 'Pending'
        CHECK (erp_sync_status IN ('Pending', 'Synced', 'Failed', 'Ignored')),
    erp_sync_at TIMESTAMP,
    erp_sync_idempotency_key VARCHAR(128) UNIQUE,
    erp_response_payload JSONB,
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_prod_lots_number ON production_lots(lot_number);
CREATE INDEX IF NOT EXISTS idx_prod_lots_product ON production_lots(product_id);
CREATE INDEX IF NOT EXISTS idx_prod_lots_disposition ON production_lots(disposition);
CREATE INDEX IF NOT EXISTS idx_prod_lots_erp_sync ON production_lots(erp_sync_status);

-- Link samples to production_lots
ALTER TABLE samples ADD COLUMN IF NOT EXISTS production_lot_id INTEGER REFERENCES production_lots(id);
CREATE INDEX IF NOT EXISTS idx_samples_production_lot ON samples(production_lot_id);

-- 2. Lab Batches (Analytical Worklists / Sequences / Runs)
CREATE TABLE IF NOT EXISTS lab_batches (
    id SERIAL PRIMARY KEY,
    batch_code VARCHAR(100) UNIQUE NOT NULL,
    analysis_id INTEGER REFERENCES analyses(id),
    instrument_id INTEGER REFERENCES instruments(id),
    analyst_id INTEGER REFERENCES users(id),
    method_id INTEGER REFERENCES methods(id),
    run_date DATE DEFAULT CURRENT_DATE,
    status VARCHAR(50) DEFAULT 'Draft'
        CHECK (status IN ('Draft', 'Prepared', 'Running', 'Completed', 'QC_Passed', 'QC_Failed', 'Invalidated')),
    qc_status VARCHAR(50) DEFAULT 'Pending'
        CHECK (qc_status IN ('Pending', 'Passed', 'Failed', 'Warning')),
    qc_evaluation_notes TEXT,
    worklist_format VARCHAR(50) DEFAULT 'empower_csv',
    exported_worklist_content TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_lab_batches_code ON lab_batches(batch_code);
CREATE INDEX IF NOT EXISTS idx_lab_batches_status ON lab_batches(status);
CREATE INDEX IF NOT EXISTS idx_lab_batches_instrument ON lab_batches(instrument_id);

-- 3. Lab Batch Items (Sequence Order, Controls & Samples)
CREATE TABLE IF NOT EXISTS lab_batch_items (
    id SERIAL PRIMARY KEY,
    lab_batch_id INTEGER NOT NULL REFERENCES lab_batches(id) ON DELETE CASCADE,
    sequence_index INTEGER NOT NULL,
    item_type VARCHAR(50) NOT NULL
        CHECK (item_type IN ('blank', 'calibration_standard', 'qc_control', 'sample', 'duplicate', 'spike')),
    sample_id INTEGER REFERENCES samples(id),
    sample_test_id INTEGER REFERENCES sample_tests(id),
    control_name VARCHAR(150),
    vial_position VARCHAR(50),
    injection_volume_ul NUMERIC(8, 2) DEFAULT 10.0,
    expected_value NUMERIC(15, 6),
    tolerance_min NUMERIC(15, 6),
    tolerance_max NUMERIC(15, 6),
    actual_value NUMERIC(15, 6),
    qc_pass BOOLEAN,
    invalidation_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (lab_batch_id, sequence_index)
);

CREATE INDEX IF NOT EXISTS idx_lab_batch_items_batch ON lab_batch_items(lab_batch_id);
CREATE INDEX IF NOT EXISTS idx_lab_batch_items_sample ON lab_batch_items(sample_id);
CREATE INDEX IF NOT EXISTS idx_lab_batch_items_test ON lab_batch_items(sample_test_id);

-- 4. Backfill from legacy batches into production_lots
INSERT INTO production_lots (
    lot_number, product_id, erp_batch_number, manufacturing_date, expiry_date,
    lot_size, disposition, created_by, created_at, updated_at
)
SELECT
    b.batch_number,
    b.product_id,
    b.batch_number,
    b.manufacture_date,
    b.expiry_date,
    NULLIF(REGEXP_REPLACE(b.batch_size, '[^0-9.]', '', 'g'), '')::numeric,
    CASE
        WHEN b.status IN ('Approved', 'COA Released') THEN 'Released'
        WHEN b.status = 'Rejected' THEN 'Rejected'
        ELSE 'Pending'
    END,
    b.created_by,
    b.created_at,
    b.updated_at
FROM batches b
WHERE b.product_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM production_lots pl WHERE pl.lot_number = b.batch_number)
ON CONFLICT (lot_number) DO NOTHING;

-- Backfill samples.production_lot_id
UPDATE samples s
SET production_lot_id = pl.id
FROM production_lots pl
WHERE (s.batch_number = pl.lot_number OR s.batch_number = pl.erp_batch_number)
  AND s.production_lot_id IS NULL;

-- 5. Compatibility View for Legacy Batch Integrations
CREATE OR REPLACE VIEW v_production_lots_legacy AS
SELECT
    pl.id,
    pl.lot_number AS batch_number,
    pl.product_id,
    p.product_code,
    p.product_name,
    pl.site_id,
    pl.manufacturing_date AS manufacture_date,
    pl.expiry_date,
    pl.lot_size::text AS batch_size,
    CASE
        WHEN pl.disposition = 'Released' THEN 'Approved'
        WHEN pl.disposition = 'Rejected' THEN 'Rejected'
        ELSE 'In Progress'
    END AS status,
    pl.disposition_by AS approved_by,
    pl.disposition_at AS approved_at,
    pl.notes,
    pl.created_by,
    pl.created_at,
    pl.updated_at,
    pl.deleted_at
FROM production_lots pl
LEFT JOIN products p ON pl.product_id = p.id;
