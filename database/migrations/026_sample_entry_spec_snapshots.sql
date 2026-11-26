-- ==============================================================================
-- Migration 026: Sample Entry Follows Product & Frozen Specification Snapshots
-- Standards: 21 CFR Part 11, EU Annex 11, ISO/IEC 17025, ALCOA+
-- Capabilities:
--   1. Sample Templates (Pre-filled storage conditions, label format, receipt check)
--   2. Sample Specification & Stage Attachment (spec_version_id, sample_stage)
--   3. Sample Test Analysis Linkage & Add/Remove Audit Governance
--   4. Immutable Result Spec Snapshots (spec_min, spec_max, unit, rounding, LOQ)
--   5. Configurable Sample Status Machine with Quality Guards (OOS, Training, Receipt)
-- ==============================================================================

-- 1. Sample Templates Table
CREATE TABLE IF NOT EXISTS sample_templates (
    id SERIAL PRIMARY KEY,
    template_code VARCHAR(100) UNIQUE NOT NULL,
    template_name VARCHAR(255) NOT NULL,
    sample_type_id INTEGER REFERENCES sample_types(id),
    default_stage VARCHAR(50) DEFAULT 'Finished Good',
    storage_condition VARCHAR(100) DEFAULT 'Ambient 15-25°C',
    label_format VARCHAR(50) DEFAULT 'ZPL_2X1',
    default_priority VARCHAR(20) DEFAULT 'Normal',
    requires_receipt_check BOOLEAN DEFAULT TRUE,
    receipt_check_checklist JSONB DEFAULT '["Container Integrity Intact", "Temperature Logger Verified", "Label Legible & Correct"]'::jsonb,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_sample_templates_code ON sample_templates(template_code);

-- 2. Extend samples table with spec version, stage, template, and receipt check
ALTER TABLE samples ADD COLUMN IF NOT EXISTS spec_version_id INTEGER REFERENCES specification_versions(id);
ALTER TABLE samples ADD COLUMN IF NOT EXISTS sample_stage VARCHAR(50) DEFAULT 'Finished Good'
    CHECK (sample_stage IN ('Raw Material', 'In-Process', 'Finished Good', 'Stability', 'Release', 'Packaging'));
ALTER TABLE samples ADD COLUMN IF NOT EXISTS template_id INTEGER REFERENCES sample_templates(id);
ALTER TABLE samples ADD COLUMN IF NOT EXISTS receipt_check_status VARCHAR(50) DEFAULT 'Passed'
    CHECK (receipt_check_status IN ('Pending', 'Passed', 'Failed_Quarantine', 'Exemption'));
ALTER TABLE samples ADD COLUMN IF NOT EXISTS receipt_check_notes TEXT;
ALTER TABLE samples ADD COLUMN IF NOT EXISTS sampling_plan_schedule_id INTEGER;

CREATE INDEX IF NOT EXISTS idx_samples_spec_version ON samples(spec_version_id);
CREATE INDEX IF NOT EXISTS idx_samples_stage ON samples(sample_stage);

-- 3. Extend sample_tests table with analysis reference and audited test modifications
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS analysis_id INTEGER REFERENCES analyses(id);
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS spec_version_id INTEGER REFERENCES specification_versions(id);
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS is_mandatory BOOLEAN DEFAULT TRUE;
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS added_reason TEXT;
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS added_by INTEGER REFERENCES users(id);
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS removed_reason TEXT;
ALTER TABLE sample_tests ADD COLUMN IF NOT EXISTS removed_by INTEGER REFERENCES users(id);

CREATE INDEX IF NOT EXISTS idx_sample_tests_analysis ON sample_tests(analysis_id);

-- 4. Extend results table with frozen specification limit snapshots (ALCOA+ Immutability)
ALTER TABLE results ADD COLUMN IF NOT EXISTS spec_version_id INTEGER REFERENCES specification_versions(id);
ALTER TABLE results ADD COLUMN IF NOT EXISTS component_id INTEGER REFERENCES components(id);
ALTER TABLE results ADD COLUMN IF NOT EXISTS spec_min_snapshot NUMERIC(18,6);
ALTER TABLE results ADD COLUMN IF NOT EXISTS spec_max_snapshot NUMERIC(18,6);
ALTER TABLE results ADD COLUMN IF NOT EXISTS spec_target_snapshot NUMERIC(18,6);
ALTER TABLE results ADD COLUMN IF NOT EXISTS spec_text_snapshot VARCHAR(255);
ALTER TABLE results ADD COLUMN IF NOT EXISTS unit_snapshot VARCHAR(50);
ALTER TABLE results ADD COLUMN IF NOT EXISTS decimal_places_snapshot INTEGER DEFAULT 2;
ALTER TABLE results ADD COLUMN IF NOT EXISTS rounding_method_snapshot VARCHAR(20) DEFAULT 'half_even';
ALTER TABLE results ADD COLUMN IF NOT EXISTS loq_snapshot NUMERIC(18,6);
ALTER TABLE results ADD COLUMN IF NOT EXISTS uloq_snapshot NUMERIC(18,6);
ALTER TABLE results ADD COLUMN IF NOT EXISTS reportable_value VARCHAR(100);
ALTER TABLE results ADD COLUMN IF NOT EXISTS is_loq BOOLEAN DEFAULT FALSE;
ALTER TABLE results ADD COLUMN IF NOT EXISTS is_uloq BOOLEAN DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS idx_results_spec_version ON results(spec_version_id);
CREATE INDEX IF NOT EXISTS idx_results_component ON results(component_id);

-- 5. Configurable Sample Lifecycle Status Machine with Quality Guards
CREATE TABLE IF NOT EXISTS sample_lifecycle_transitions (
    id SERIAL PRIMARY KEY,
    sample_type_id INTEGER REFERENCES sample_types(id),
    from_status VARCHAR(50) NOT NULL,
    to_status VARCHAR(50) NOT NULL,
    required_role VARCHAR(50) DEFAULT 'Analyst',
    guard_all_tests_completed BOOLEAN DEFAULT TRUE,
    guard_no_open_oos BOOLEAN DEFAULT TRUE,
    guard_training_certified BOOLEAN DEFAULT TRUE,
    guard_receipt_checked BOOLEAN DEFAULT TRUE,
    requires_esignature BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (sample_type_id, from_status, to_status)
);

-- 6. Seed Sample Templates
INSERT INTO sample_templates (template_code, template_name, default_stage, storage_condition, label_format, requires_receipt_check)
VALUES
    ('TMPL-FINISHED-GOOD', 'Finished Product Release Testing', 'Finished Good', 'Ambient 15-25°C', 'ZPL_2X1', TRUE),
    ('TMPL-RAW-MATERIAL', 'Incoming Raw Material Quarantine & Assay', 'Raw Material', 'Controlled Room Temperature', 'ZPL_2X1', TRUE),
    ('TMPL-IN-PROCESS', 'In-Process Quality Control (IPQC)', 'In-Process', 'Ambient 15-25°C', 'ZPL_2X1', FALSE),
    ('TMPL-STABILITY-ICH', 'ICH Accelerated & Long-Term Stability Specimen', 'Stability', '2-8°C Controlled Cold Room', 'ZPL_2X1', TRUE)
ON CONFLICT (template_code) DO NOTHING;

-- 7. Seed Default Lifecycle Guard Transitions
INSERT INTO sample_lifecycle_transitions
    (sample_type_id, from_status, to_status, required_role, guard_all_tests_completed, guard_no_open_oos, guard_training_certified, guard_receipt_checked, requires_esignature)
VALUES
    (NULL, 'Registered', 'In Progress', 'Analyst', FALSE, FALSE, FALSE, TRUE, FALSE),
    (NULL, 'In Progress', 'Completed', 'Analyst', TRUE, FALSE, TRUE, TRUE, FALSE),
    (NULL, 'Completed', 'Reviewed', 'Reviewer', TRUE, FALSE, TRUE, TRUE, FALSE),
    (NULL, 'Reviewed', 'Approved', 'Approver', TRUE, TRUE, TRUE, TRUE, TRUE),
    (NULL, 'Reviewed', 'Rejected', 'Approver', FALSE, FALSE, FALSE, FALSE, TRUE)
ON CONFLICT DO NOTHING;

-- 8. Backfill existing samples with active approved spec versions
UPDATE samples s
SET spec_version_id = (
    SELECT sv.id
    FROM specifications sp
    JOIN specification_versions sv ON sv.specification_id = sp.id
    WHERE sp.product_id = s.product_id AND sv.status = 'Approved'
    ORDER BY sv.version_number DESC LIMIT 1
)
WHERE s.product_id IS NOT NULL AND s.spec_version_id IS NULL;

-- 9. Backfill sample_tests analysis_id from test_code
UPDATE sample_tests st
SET analysis_id = a.id
FROM tests t
JOIN analyses a ON a.analysis_code = t.test_code
WHERE st.test_id = t.id AND st.analysis_id IS NULL;
