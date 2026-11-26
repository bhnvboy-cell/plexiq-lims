-- ==============================================================================
-- Migration 025: Commercial Master Data Spine (4-Layer Product-Spec Architecture)
-- Standards: 21 CFR Part 11, EU Annex 11, GAMP 5, ISO/IEC 17025
-- Layers:
--   1. Products (Code, ERP Material Number, Site Scope, Stage Status)
--   2. Versioned Specifications (Draft, Approved, Superseded, E-Signature, Change-Control)
--   3. Versioned Analyses (Methods, SOP, Instrument Eligibility, Training Qualification)
--   4. Components / Analytes (Data types, ASTM E29 rounding, LOQ/ULOQ, formulas)
--   5. Specification Limits & Product Test Plans (Stage routing: Raw, In-Process, Finished, Stability)
-- ==============================================================================

-- 1. Extend products with commercial enterprise attributes
ALTER TABLE products ADD COLUMN IF NOT EXISTS product_type VARCHAR(50) DEFAULT 'Finished Good';
ALTER TABLE products ADD COLUMN IF NOT EXISTS erp_material_number VARCHAR(100);
ALTER TABLE products ADD COLUMN IF NOT EXISTS site_id INTEGER REFERENCES sites(id);
ALTER TABLE products ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Active'
    CHECK (status IN ('Active', 'Draft', 'Obsolete', 'Discontinued'));

-- 2. Specifications Header
CREATE TABLE IF NOT EXISTS specifications (
    id SERIAL PRIMARY KEY,
    spec_code VARCHAR(100) UNIQUE NOT NULL,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    grade VARCHAR(100),
    customer_id INTEGER REFERENCES customers(id),
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_specifications_product ON specifications(product_id);
CREATE INDEX IF NOT EXISTS idx_specifications_code ON specifications(spec_code);

-- 3. Specification Versions (Immutable when Approved)
CREATE TABLE IF NOT EXISTS specification_versions (
    id SERIAL PRIMARY KEY,
    specification_id INTEGER NOT NULL REFERENCES specifications(id) ON DELETE CASCADE,
    version_number INTEGER NOT NULL DEFAULT 1,
    effective_from DATE NOT NULL,
    effective_to DATE,
    status VARCHAR(50) NOT NULL DEFAULT 'Draft'
        CHECK (status IN ('Draft', 'Approved', 'Superseded', 'Archived')),
    change_control_id INTEGER REFERENCES change_controls(id),
    approved_by INTEGER REFERENCES users(id),
    approved_at TIMESTAMP,
    approval_signature_hash VARCHAR(64),
    approval_reason TEXT,
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (specification_id, version_number)
);

CREATE INDEX IF NOT EXISTS idx_spec_versions_spec_status ON specification_versions(specification_id, status);
CREATE INDEX IF NOT EXISTS idx_spec_versions_effective ON specification_versions(effective_from, effective_to);

-- 4. Analyses (Tests & Analytical Methods)
CREATE TABLE IF NOT EXISTS analyses (
    id SERIAL PRIMARY KEY,
    analysis_code VARCHAR(100) UNIQUE NOT NULL,
    analysis_name VARCHAR(255) NOT NULL,
    method_id INTEGER REFERENCES methods(id),
    sop_reference VARCHAR(255),
    category VARCHAR(100) DEFAULT 'General',
    allowed_instrument_types JSONB DEFAULT '[]'::jsonb,
    required_qualification_code VARCHAR(100),
    expected_duration_minutes INTEGER DEFAULT 60,
    default_replicate_count INTEGER DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_analyses_code ON analyses(analysis_code);

-- 5. Components (Analytes, Parameters, LOQ/ULOQ, Rounding)
CREATE TABLE IF NOT EXISTS components (
    id SERIAL PRIMARY KEY,
    component_code VARCHAR(100) UNIQUE NOT NULL,
    component_name VARCHAR(255) NOT NULL,
    analysis_id INTEGER REFERENCES analyses(id) ON DELETE SET NULL,
    unit VARCHAR(50),
    data_type VARCHAR(20) NOT NULL DEFAULT 'numeric'
        CHECK (data_type IN ('numeric', 'text', 'picklist', 'boolean', 'date', 'range')),
    decimal_places INTEGER DEFAULT 2,
    rounding_method VARCHAR(20) DEFAULT 'half_even'
        CHECK (rounding_method IN ('half_even', 'half_up', 'truncate')),
    significant_figures INTEGER,
    loq NUMERIC(18,6),
    uloq NUMERIC(18,6),
    lod NUMERIC(18,6),
    reportable_range_min NUMERIC(18,6),
    reportable_range_max NUMERIC(18,6),
    formula_id INTEGER REFERENCES calculation_formulas(id),
    picklist_options JSONB DEFAULT '[]'::jsonb,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_components_code ON components(component_code);
CREATE INDEX IF NOT EXISTS idx_components_analysis ON components(analysis_id);

-- 6. Specification Limits (Component rules for each versioned specification)
CREATE TABLE IF NOT EXISTS specification_limits (
    id SERIAL PRIMARY KEY,
    spec_version_id INTEGER NOT NULL REFERENCES specification_versions(id) ON DELETE CASCADE,
    component_id INTEGER NOT NULL REFERENCES components(id) ON DELETE CASCADE,
    limit_type VARCHAR(20) NOT NULL DEFAULT 'range'
        CHECK (limit_type IN ('range', 'min', 'max', 'text_match', 'pass_fail')),
    min_limit NUMERIC(18,6),
    max_limit NUMERIC(18,6),
    target_value NUMERIC(18,6),
    text_match_expected VARCHAR(255),
    warn_min NUMERIC(18,6),
    warn_max NUMERIC(18,6),
    display_specification VARCHAR(255),
    oot_rule VARCHAR(100),
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (spec_version_id, component_id)
);

CREATE INDEX IF NOT EXISTS idx_spec_limits_version ON specification_limits(spec_version_id);
CREATE INDEX IF NOT EXISTS idx_spec_limits_comp ON specification_limits(component_id);

-- 7. Product Test Plans (Stage routing: which analyses run per product & stage)
CREATE TABLE IF NOT EXISTS product_test_plans (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    stage VARCHAR(50) NOT NULL
        CHECK (stage IN ('Raw Material', 'In-Process', 'Finished Good', 'Stability', 'Release', 'Packaging')),
    sample_type VARCHAR(50) DEFAULT 'Standard',
    analysis_id INTEGER NOT NULL REFERENCES analyses(id) ON DELETE CASCADE,
    is_mandatory BOOLEAN DEFAULT TRUE,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (product_id, stage, analysis_id)
);

CREATE INDEX IF NOT EXISTS idx_product_test_plans_routing ON product_test_plans(product_id, stage);

-- 8. Immutability Protection for Approved Specification Versions (ALCOA+ & 21 CFR Part 11)
CREATE OR REPLACE FUNCTION enforce_spec_version_immutability() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'UPDATE' THEN
        IF OLD.status = 'Approved' AND (NEW.status = 'Approved' OR NEW.status = 'Draft') THEN
            -- Allow superseding or archiving, but never alter effective dates or core spec data of an approved version
            IF (OLD.version_number != NEW.version_number OR OLD.effective_from != NEW.effective_from OR OLD.specification_id != NEW.specification_id) THEN
                RAISE EXCEPTION 'Specification Version id=% is Approved and immutable. Create a new draft specification version instead.', OLD.id;
            END IF;
        END IF;
    ELSIF TG_OP = 'DELETE' THEN
        IF OLD.status = 'Approved' THEN
            RAISE EXCEPTION 'Approved Specification Version id=% cannot be deleted. Archive or supersede it.', OLD.id;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_spec_version_immutability ON specification_versions;
CREATE TRIGGER trg_spec_version_immutability
BEFORE UPDATE OR DELETE ON specification_versions
FOR EACH ROW EXECUTE FUNCTION enforce_spec_version_immutability();

-- 9. Backward Compatibility Backfill
-- A. Seed analyses from legacy tests table
INSERT INTO analyses (analysis_code, analysis_name, method_id, sop_reference, category, is_active)
SELECT t.test_code, t.test_name, t.method_id, 'SOP-' || t.test_code, 'General', t.is_active
FROM tests t
ON CONFLICT (analysis_code) DO NOTHING;

-- B. Seed components from analysis_parameters (or tests fallback)
INSERT INTO components (component_code, component_name, analysis_id, unit, data_type, decimal_places, is_active)
SELECT 
    ap.parameter_code, 
    ap.parameter_name, 
    a.id, 
    ap.unit, 
    CASE WHEN ap.data_type IN ('numeric','text','boolean') THEN ap.data_type ELSE 'numeric' END,
    ap.decimal_places,
    ap.is_active
FROM analysis_parameters ap
LEFT JOIN analyses a ON a.analysis_code = ap.parameter_code
ON CONFLICT (component_code) DO NOTHING;

-- Fallback components from tests not yet in analysis_parameters
INSERT INTO components (component_code, component_name, analysis_id, unit, data_type, decimal_places, is_active)
SELECT 
    t.test_code, 
    t.test_name, 
    a.id, 
    u.unit_code, 
    'numeric', 
    2, 
    t.is_active
FROM tests t
JOIN analyses a ON a.analysis_code = t.test_code
LEFT JOIN units u ON t.unit_id = u.id
ON CONFLICT (component_code) DO NOTHING;

-- C. For each product, create baseline specification & version 1.0 (Approved)
INSERT INTO specifications (spec_code, product_id, grade)
SELECT 'SPEC-' || p.product_code, p.id, COALESCE(p.category, 'Standard')
FROM products p
ON CONFLICT (spec_code) DO NOTHING;

INSERT INTO specification_versions (specification_id, version_number, effective_from, status, approval_reason, notes)
SELECT s.id, 1, CURRENT_DATE - INTERVAL '30 days', 'Approved', 'Initial Commercial Master Data Baseline Migration', 'Automated backfill from products_tests'
FROM specifications s
WHERE NOT EXISTS (
    SELECT 1 FROM specification_versions sv WHERE sv.specification_id = s.id AND sv.version_number = 1
);

-- D. Populate specification_limits from products_tests
INSERT INTO specification_limits (spec_version_id, component_id, limit_type, min_limit, max_limit, display_specification)
SELECT 
    sv.id,
    c.id,
    CASE 
        WHEN pt.min_spec_limit IS NOT NULL AND pt.max_spec_limit IS NOT NULL THEN 'range'
        WHEN pt.min_spec_limit IS NOT NULL THEN 'min'
        WHEN pt.max_spec_limit IS NOT NULL THEN 'max'
        ELSE 'text_match'
    END,
    pt.min_spec_limit,
    pt.max_spec_limit,
    COALESCE(pt.spec_limit_text, pt.min_spec_limit || ' - ' || pt.max_spec_limit || ' ' || COALESCE(c.unit, ''))
FROM products_tests pt
JOIN specifications s ON s.product_id = pt.product_id
JOIN specification_versions sv ON sv.specification_id = s.id AND sv.version_number = 1
JOIN tests t ON pt.test_id = t.id
JOIN components c ON c.component_code = t.test_code
ON CONFLICT (spec_version_id, component_id) DO NOTHING;

-- E. Populate product_test_plans (default stages: Finished Good, Release, In-Process)
INSERT INTO product_test_plans (product_id, stage, analysis_id, is_mandatory, sort_order)
SELECT 
    pt.product_id,
    'Finished Good',
    a.id,
    TRUE,
    pt.sort_order
FROM products_tests pt
JOIN tests t ON pt.test_id = t.id
JOIN analyses a ON a.analysis_code = t.test_code
ON CONFLICT (product_id, stage, analysis_id) DO NOTHING;

INSERT INTO product_test_plans (product_id, stage, analysis_id, is_mandatory, sort_order)
SELECT 
    pt.product_id,
    'Release',
    a.id,
    TRUE,
    pt.sort_order
FROM products_tests pt
JOIN tests t ON pt.test_id = t.id
JOIN analyses a ON a.analysis_code = t.test_code
ON CONFLICT (product_id, stage, analysis_id) DO NOTHING;
