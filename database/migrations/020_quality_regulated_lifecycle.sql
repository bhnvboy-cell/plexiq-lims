-- ====================================================================
-- Migration: 020_quality_regulated_lifecycle.sql
-- Description: Phase 3 Quality & Regulated Lifecycle for PlexiQ LIMS
--   1. Change Control & Customer Complaints
--   2. FMEA Failure Mode & Effects Analysis Risk Engine
--   3. SOP Document Control & Controlled Copy Distribution
--   4. Training Gating & Method/Instrument Qualification Records
--   5. Equipment Qualifications (DQ/IQ/OQ/PQ) & Out-of-Cal Impact Assessments
--   6. ISO 17025 Measurement Uncertainty Budgets & Components
-- ====================================================================

-- 1. CHANGE CONTROL WORKFLOW (21 CFR Part 211.100, EU Annex 11)
CREATE TABLE IF NOT EXISTS change_controls (
    id SERIAL PRIMARY KEY,
    change_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    change_type VARCHAR(50) NOT NULL CHECK (change_type IN ('Method', 'Equipment', 'Specification', 'Process', 'Software', 'Facility')),
    reason_for_change TEXT NOT NULL,
    impact_assessment TEXT NOT NULL,
    proposed_by INTEGER REFERENCES users(id),
    pre_approved_by INTEGER REFERENCES users(id),
    pre_approved_at TIMESTAMP,
    implemented_by INTEGER REFERENCES users(id),
    implemented_at TIMESTAMP,
    post_approved_by INTEGER REFERENCES users(id),
    post_approved_at TIMESTAMP,
    status VARCHAR(30) DEFAULT 'Draft' CHECK (status IN ('Draft', 'Submitted', 'Pre-Approved', 'Implemented', 'Post-Approved', 'Rejected', 'Closed')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_change_controls_status ON change_controls(status);

-- CUSTOMER COMPLAINTS
CREATE TABLE IF NOT EXISTS customer_complaints (
    id SERIAL PRIMARY KEY,
    complaint_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    sample_id INTEGER REFERENCES samples(id) ON DELETE SET NULL,
    batch_number VARCHAR(100),
    complaint_type VARCHAR(50) NOT NULL CHECK (complaint_type IN ('OOS_Result', 'Packaging', 'Labeling', 'Contamination', 'Delayed_TAT', 'Other')),
    received_date DATE NOT NULL DEFAULT CURRENT_DATE,
    description TEXT NOT NULL,
    risk_level VARCHAR(20) DEFAULT 'Medium' CHECK (risk_level IN ('Low', 'Medium', 'High', 'Critical')),
    root_cause TEXT,
    capa_id INTEGER REFERENCES capa_records(id) ON DELETE SET NULL,
    status VARCHAR(30) DEFAULT 'Open' CHECK (status IN ('Open', 'Under Investigation', 'CAPA Initiated', 'Closed')),
    assigned_to INTEGER REFERENCES users(id),
    closed_at TIMESTAMP,
    closed_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_complaints_customer ON customer_complaints(customer_id);

-- 2. FMEA RISK ENGINE (Failure Mode & Effects Analysis: RPN = S * O * D)
CREATE TABLE IF NOT EXISTS fmea_risk_assessments (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL, -- 'test', 'instrument', 'method', 'sample', 'change_control', 'deviation'
    entity_id INTEGER NOT NULL,
    process_step VARCHAR(255) NOT NULL,
    failure_mode TEXT NOT NULL,
    failure_effect TEXT NOT NULL,
    failure_cause TEXT NOT NULL,
    severity INTEGER NOT NULL CHECK (severity BETWEEN 1 AND 10),
    occurrence INTEGER NOT NULL CHECK (occurrence BETWEEN 1 AND 10),
    detection INTEGER NOT NULL CHECK (detection BETWEEN 1 AND 10),
    rpn INTEGER GENERATED ALWAYS AS (severity * occurrence * detection) STORED,
    mitigation_plan TEXT,
    revised_severity INTEGER CHECK (revised_severity BETWEEN 1 AND 10),
    revised_occurrence INTEGER CHECK (revised_occurrence BETWEEN 1 AND 10),
    revised_detection INTEGER CHECK (revised_detection BETWEEN 1 AND 10),
    revised_rpn INTEGER,
    assessed_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_fmea_entity ON fmea_risk_assessments(entity_type, entity_id);

-- 3. SOP DOCUMENT CONTROL & CONTROLLED COPIES (ISO 17025 / 21 CFR 211.180)
CREATE TABLE IF NOT EXISTS sops (
    id SERIAL PRIMARY KEY,
    sop_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    version VARCHAR(20) NOT NULL DEFAULT '1.0',
    effective_date DATE,
    review_due_date DATE,
    author_id INTEGER REFERENCES users(id),
    approver_id INTEGER REFERENCES users(id),
    approved_at TIMESTAMP,
    status VARCHAR(30) DEFAULT 'Draft' CHECK (status IN ('Draft', 'Under Review', 'Approved', 'Effective', 'Superseded', 'Obsolete')),
    file_path VARCHAR(500),
    sha256_checksum VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sop_controlled_copies (
    id SERIAL PRIMARY KEY,
    sop_id INTEGER NOT NULL REFERENCES sops(id) ON DELETE CASCADE,
    copy_number INTEGER NOT NULL,
    issued_to INTEGER NOT NULL REFERENCES users(id),
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at TIMESTAMP,
    is_recalled BOOLEAN DEFAULT FALSE,
    recalled_at TIMESTAMP,
    recalled_by INTEGER REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_sop_copies_sop ON sop_controlled_copies(sop_id);
CREATE INDEX IF NOT EXISTS idx_sop_copies_user ON sop_controlled_copies(issued_to);

-- 4. TRAINING GATING QUALIFICATIONS (Block unqualified analysts)
CREATE TABLE IF NOT EXISTS training_qualifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    qualification_type VARCHAR(30) NOT NULL CHECK (qualification_type IN ('test_method', 'instrument', 'sop')),
    entity_id INTEGER NOT NULL, -- references tests(id), instruments(id), or sops(id)
    qualified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    certified_by INTEGER REFERENCES users(id),
    status VARCHAR(20) DEFAULT 'Qualified' CHECK (status IN ('Qualified', 'Expired', 'Revoked', 'In Training')),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_user_qualifications_unique ON training_qualifications(user_id, qualification_type, entity_id);

-- 5. EQUIPMENT QUALIFICATION (DQ/IQ/OQ/PQ) & OUT-OF-CAL IMPACT
CREATE TABLE IF NOT EXISTS instrument_qualifications (
    id SERIAL PRIMARY KEY,
    instrument_id INTEGER NOT NULL REFERENCES instruments(id) ON DELETE CASCADE,
    phase VARCHAR(10) NOT NULL CHECK (phase IN ('DQ', 'IQ', 'OQ', 'PQ')),
    protocol_code VARCHAR(100) NOT NULL,
    executed_by INTEGER REFERENCES users(id),
    approved_by INTEGER REFERENCES users(id),
    execution_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'Passed' CHECK (status IN ('Passed', 'Failed', 'Conditional')),
    report_path VARCHAR(500),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS calibration_impact_assessments (
    id SERIAL PRIMARY KEY,
    instrument_id INTEGER NOT NULL REFERENCES instruments(id) ON DELETE CASCADE,
    calibration_record_id INTEGER,
    failed_date DATE NOT NULL,
    last_good_calibration_date DATE,
    affected_tests_count INTEGER DEFAULT 0,
    risk_level VARCHAR(20) DEFAULT 'High' CHECK (risk_level IN ('Low', 'Medium', 'High', 'Critical')),
    disposition VARCHAR(30) DEFAULT 'Pending Review' CHECK (disposition IN ('Pending Review', 'Results Validated', 'Results Invalidated - Retest', 'Recall Initiated')),
    corrective_actions TEXT,
    evaluated_by INTEGER REFERENCES users(id),
    approved_by INTEGER REFERENCES users(id),
    evaluated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. ISO 17025 MEASUREMENT UNCERTAINTY BUDGETS
CREATE TABLE IF NOT EXISTS uncertainty_budgets (
    id SERIAL PRIMARY KEY,
    test_id INTEGER NOT NULL REFERENCES tests(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(30),
    coverage_factor_k NUMERIC(4,2) DEFAULT 2.0, -- k=2 for ~95.45% confidence
    confidence_level_pct NUMERIC(5,2) DEFAULT 95.45,
    combined_uncertainty NUMERIC(18,6),
    expanded_uncertainty NUMERIC(18,6),
    is_active BOOLEAN DEFAULT TRUE,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS uncertainty_components (
    id SERIAL PRIMARY KEY,
    budget_id INTEGER NOT NULL REFERENCES uncertainty_budgets(id) ON DELETE CASCADE,
    source_name VARCHAR(150) NOT NULL,
    component_type VARCHAR(20) NOT NULL CHECK (component_type IN ('Type_A', 'Type_B')),
    distribution VARCHAR(30) NOT NULL CHECK (distribution IN ('Normal', 'Rectangular', 'Triangular', 'U-Shaped')),
    value NUMERIC(18,6) NOT NULL,
    divisor NUMERIC(10,4) NOT NULL DEFAULT 1.0, -- e.g. sqrt(3)=1.732 for rectangular, sqrt(6)=2.449 for triangular
    standard_uncertainty NUMERIC(18,6) NOT NULL,
    sensitivity_coefficient NUMERIC(10,4) DEFAULT 1.0,
    degrees_of_freedom INTEGER DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
