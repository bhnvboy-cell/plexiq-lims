-- ====================================================================
-- Migration: 019_core_lims_depth.sql
-- Description: Core LIMS Depth for PlexiQ (Phase 2)
--   1. Sample Hierarchy & Lineage (aliquots, sub-samples, composites)
--   2. Multi-tier Cold-Chain Storage & Box Coordinates (A1-H12)
--   3. Sandboxed Formula Calculation Engine & ASTM E29 Sig-Figs
--   4. Out-of-Trend (OOT) Detection & Statistical Monitoring
--   5. Industrial Label Template Engine (ZPL, Brother, CUPS)
--   6. Visual Drag-and-Drop COA Layout Schema
--   7. Turnaround Time (TAT) & SLA Escalation Monitoring
-- ====================================================================

-- 1. SAMPLE HIERARCHY & GENEALOGY LINEAGE
ALTER TABLE samples ADD COLUMN IF NOT EXISTS parent_sample_id INTEGER REFERENCES samples(id) ON DELETE SET NULL;
ALTER TABLE samples ADD COLUMN IF NOT EXISTS hierarchy_type VARCHAR(20) DEFAULT 'primary';
ALTER TABLE samples ADD COLUMN IF NOT EXISTS genealogy_path VARCHAR(255) DEFAULT '';
ALTER TABLE samples ADD COLUMN IF NOT EXISTS aliquot_index INTEGER DEFAULT 0;
ALTER TABLE samples ADD COLUMN IF NOT EXISTS composite_components JSONB DEFAULT '[]';

CREATE INDEX IF NOT EXISTS idx_samples_parent_id ON samples(parent_sample_id);
CREATE INDEX IF NOT EXISTS idx_samples_genealogy_path ON samples(genealogy_path);

-- 2. MULTI-TIER STORAGE & COLD-CHAIN COORDINATES
CREATE TABLE IF NOT EXISTS storage_locations (
    id SERIAL PRIMARY KEY,
    parent_id INTEGER REFERENCES storage_locations(id) ON DELETE CASCADE,
    location_type VARCHAR(30) NOT NULL CHECK (location_type IN ('facility', 'room', 'freezer', 'refrigerator', 'incubator', 'shelf', 'rack', 'box', 'plate', 'ambient')),
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    temperature_nominal NUMERIC(5,2),
    temperature_min NUMERIC(5,2),
    temperature_max NUMERIC(5,2),
    capacity_rows INTEGER DEFAULT 1,
    capacity_cols INTEGER DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_storage_locations_parent ON storage_locations(parent_id);
CREATE INDEX IF NOT EXISTS idx_storage_locations_code ON storage_locations(code);

CREATE TABLE IF NOT EXISTS sample_storage_assignments (
    id SERIAL PRIMARY KEY,
    sample_id INTEGER NOT NULL REFERENCES samples(id) ON DELETE CASCADE,
    storage_location_id INTEGER NOT NULL REFERENCES storage_locations(id) ON DELETE RESTRICT,
    row_coord INTEGER DEFAULT 1,
    col_coord INTEGER DEFAULT 1,
    position_label VARCHAR(20),
    checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_in_by INTEGER REFERENCES users(id),
    checked_out_at TIMESTAMP,
    checked_out_by INTEGER REFERENCES users(id),
    checkout_reason TEXT,
    status VARCHAR(20) DEFAULT 'Stored' CHECK (status IN ('Stored', 'Checked Out', 'Disposed', 'Transferred'))
);

CREATE INDEX IF NOT EXISTS idx_sample_storage_sample ON sample_storage_assignments(sample_id);
CREATE INDEX IF NOT EXISTS idx_sample_storage_loc ON sample_storage_assignments(storage_location_id);

CREATE TABLE IF NOT EXISTS storage_temperature_logs (
    id SERIAL PRIMARY KEY,
    storage_location_id INTEGER NOT NULL REFERENCES storage_locations(id) ON DELETE CASCADE,
    temperature NUMERIC(5,2) NOT NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_excursion BOOLEAN DEFAULT FALSE,
    logged_by INTEGER REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_storage_temp_loc ON storage_temperature_logs(storage_location_id, logged_at DESC);

-- 3. SANDBOXED CALCULATION FORMULAS & ASTM E29 SIG-FIGS
CREATE TABLE IF NOT EXISTS calculation_formulas (
    id SERIAL PRIMARY KEY,
    test_id INTEGER REFERENCES tests(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    expression TEXT NOT NULL,
    variables JSONB NOT NULL DEFAULT '[]',
    sig_figs INTEGER DEFAULT 3,
    rounding_mode VARCHAR(20) DEFAULT 'half_even' CHECK (rounding_mode IN ('half_even', 'half_up', 'truncate')),
    version INTEGER DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_calc_formulas_test ON calculation_formulas(test_id);

-- 4. OUT-OF-TREND (OOT) DETECTION
CREATE TABLE IF NOT EXISTS oot_rules (
    id SERIAL PRIMARY KEY,
    test_id INTEGER REFERENCES tests(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
    rule_type VARCHAR(50) NOT NULL CHECK (rule_type IN ('nelson_1', 'nelson_2', 'nelson_3', 'moving_range', 'historical_sigma')),
    threshold_sigma NUMERIC(4,2) DEFAULT 3.0,
    window_size INTEGER DEFAULT 10,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS oot_incidents (
    id SERIAL PRIMARY KEY,
    sample_test_id INTEGER NOT NULL REFERENCES sample_tests(id) ON DELETE CASCADE,
    rule_type VARCHAR(50) NOT NULL,
    measured_value NUMERIC(18,6),
    expected_mean NUMERIC(18,6),
    sigma NUMERIC(18,6),
    status VARCHAR(20) DEFAULT 'Open' CHECK (status IN ('Open', 'Under Review', 'Closed', 'Escalated_To_CAPA')),
    flagged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    investigation_notes TEXT
);

CREATE INDEX IF NOT EXISTS idx_oot_incidents_test ON oot_incidents(sample_test_id);

-- 5. INDUSTRIAL LABEL TEMPLATES (ZPL, Brother, SVG)
CREATE TABLE IF NOT EXISTS label_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    label_type VARCHAR(50) NOT NULL CHECK (label_type IN ('sample', 'aliquot', 'batch', 'reagent', 'storage_box', 'equipment')),
    output_format VARCHAR(20) NOT NULL DEFAULT 'zpl' CHECK (output_format IN ('zpl', 'epl', 'tspl', 'svg_qr', 'html')),
    width_mm INTEGER NOT NULL DEFAULT 50,
    height_mm INTEGER NOT NULL DEFAULT 25,
    template_body TEXT NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default standard 50x25mm ZPL template
INSERT INTO label_templates (name, label_type, output_format, width_mm, height_mm, template_body, is_default)
VALUES (
    'Standard Sample Vial 50x25mm (ZPL)',
    'sample',
    'zpl',
    50,
    25,
    '^XA^PW400^LL200^FO20,20^BQN,2,4^FDQA,{SAMPLE_CODE}^FS^FO140,20^A0N,28,28^FD{SAMPLE_CODE}^FS^FO140,55^A0N,20,20^FD{PRODUCT_NAME}^FS^FO140,85^A0N,18,18^FDBatch: {BATCH_NUMBER}^FS^FO140,115^A0N,16,16^FDRec: {RECEIVED_DATE} | Exp: {EXPIRY_DATE}^FS^FO140,145^A0N,18,18^FDStatus: {STATUS}^FS^XZ',
    TRUE
) ON CONFLICT DO NOTHING;

-- 6. VISUAL DRAG-AND-DROP COA LAYOUT SCHEMA
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS layout_schema JSONB DEFAULT '{}';

-- 7. TAT & SLA ESCALATION TRACKING
ALTER TABLE tests ADD COLUMN IF NOT EXISTS sla_hours INTEGER DEFAULT 48;
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS target_completion_at TIMESTAMP;
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP;
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS tat_breached BOOLEAN DEFAULT FALSE;
