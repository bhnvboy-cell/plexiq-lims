-- ====================================================================
-- Migration: 023_differentiators_modern_ux.sql
-- Description: Phase 6 Competitive Differentiators & Modern UX
--   1. Dynamic No-Code Form & Schema Promotion Engine
--   2. AI-Assisted Root Cause & CAPA Quality Advisor
--   3. Validation-as-Code Requirement Traceability Matrix (RTM)
-- ====================================================================

-- 1. DYNAMIC NO-CODE FORM SCHEMAS
CREATE TABLE IF NOT EXISTS dynamic_form_schemas (
    id SERIAL PRIMARY KEY,
    schema_code VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    status VARCHAR(30) DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'archived', 'deprecated')),
    fields_schema JSONB NOT NULL DEFAULT '[]',
    layout_schema JSONB NOT NULL DEFAULT '{}',
    validation_rules JSONB NOT NULL DEFAULT '{}',
    created_by INTEGER REFERENCES users(id),
    published_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_dynamic_schemas_entity ON dynamic_form_schemas(entity_type, status);

-- 2. ENVIRONMENT PROMOTION (DEV -> UAT -> PROD with cryptographic signing)
CREATE TABLE IF NOT EXISTS environment_promotions (
    id SERIAL PRIMARY KEY,
    package_code VARCHAR(100) UNIQUE NOT NULL,
    source_environment VARCHAR(50) NOT NULL,
    target_environment VARCHAR(50) NOT NULL,
    package_type VARCHAR(50) NOT NULL,
    payload_bundle JSONB NOT NULL,
    sha256_checksum VARCHAR(64) NOT NULL,
    signature_payload TEXT,
    status VARCHAR(30) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'applied', 'rejected', 'rolled_back')),
    promoted_by INTEGER REFERENCES users(id),
    approved_by INTEGER REFERENCES users(id),
    applied_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_env_promotions_status ON environment_promotions(status);

-- 3. AI-ASSISTED ROOT CAUSE & CAPA QUALITY SUGGESTIONS (Ishikawa 6M + 5-Whys)
CREATE TABLE IF NOT EXISTS ai_quality_suggestions (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL CHECK (entity_type IN ('oos', 'deviation', 'complaint', 'capa')),
    entity_id INTEGER NOT NULL,
    ishikawa_category VARCHAR(30) NOT NULL CHECK (ishikawa_category IN ('method', 'machine', 'material', 'measurement', 'man', 'milieu_environment')),
    root_cause_hypothesis TEXT NOT NULL,
    five_whys_chain JSONB DEFAULT '[]',
    recommended_preventive_actions JSONB DEFAULT '[]',
    confidence_score NUMERIC(5,2) DEFAULT 0.85,
    ai_drafted BOOLEAN DEFAULT TRUE,
    review_status VARCHAR(30) DEFAULT 'pending' CHECK (review_status IN ('pending', 'accepted', 'rejected', 'modified')),
    reviewed_by INTEGER REFERENCES users(id),
    reviewed_at TIMESTAMP,
    reviewer_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_ai_suggestions_entity ON ai_quality_suggestions(entity_type, entity_id);

-- 4. VALIDATION-AS-CODE: REQUIREMENTS TRACEABILITY MATRIX (RTM)
CREATE TABLE IF NOT EXISTS validation_requirements (
    id SERIAL PRIMARY KEY,
    req_code VARCHAR(50) UNIQUE NOT NULL,
    req_type VARCHAR(20) NOT NULL CHECK (req_type IN ('URS', 'FS', 'DS')),
    category VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    gamp_category INTEGER DEFAULT 4,
    test_method VARCHAR(255),
    verification_status VARCHAR(30) DEFAULT 'passed' CHECK (verification_status IN ('passed', 'failed', 'pending', 'waived')),
    last_verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_val_requirements_code ON validation_requirements(req_code);
CREATE INDEX IF NOT EXISTS idx_val_requirements_category ON validation_requirements(category);
