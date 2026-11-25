-- ====================================================================
-- Migration: 018_foundation_hardening.sql
-- Description: Foundation hardening for PlexiQ LIMS (Phase 1)
--   1. Strict tamper-evident audit trail trigger (zero UPDATE/DELETE bypass)
--   2. Dedicated audit_verifications ledger
--   3. Granular RBAC permissions, role_permissions, user_permissions
--   4. Password policy, history, and forced-change flags
--   5. WebAuthn FIDO2 credentials storage
-- ====================================================================

-- 1. STRICT AUDIT LOG IMMUTABILITY
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS reason TEXT;

CREATE OR REPLACE FUNCTION block_audit_mutation() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'UPDATE' THEN
        RAISE EXCEPTION 'Audit log records are immutable (ALCOA+). Record id=% cannot be modified.', OLD.id;
    ELSIF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Audit log records are immutable (ALCOA+). Record id=% cannot be deleted.', OLD.id;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_immutable ON audit_logs;
CREATE TRIGGER trg_audit_immutable
    BEFORE UPDATE OR DELETE ON audit_logs
    FOR EACH ROW EXECUTE FUNCTION block_audit_mutation();

-- 2. DEDICATED AUDIT INTEGRITY VERIFICATION LEDGER
CREATE TABLE IF NOT EXISTS audit_verifications (
    id SERIAL PRIMARY KEY,
    verified_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    total_records INTEGER NOT NULL,
    status VARCHAR(20) NOT NULL, -- 'passed', 'failed'
    issues JSONB DEFAULT '[]',
    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_audit_verifications_verified_at ON audit_verifications(verified_at DESC);

-- 3. GRANULAR RBAC PERMISSION MATRIX
CREATE TABLE IF NOT EXISTS permissions (
    id SERIAL PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    code VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS role_permissions (
    id SERIAL PRIMARY KEY,
    role_id INTEGER REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INTEGER REFERENCES permissions(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(role_id, permission_id)
);

CREATE TABLE IF NOT EXISTS user_permissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    permission_id INTEGER REFERENCES permissions(id) ON DELETE CASCADE,
    granted BOOLEAN DEFAULT TRUE,
    site_id INTEGER NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, permission_id)
);

CREATE INDEX IF NOT EXISTS idx_role_permissions_lookup ON role_permissions(role_id, permission_id);
CREATE INDEX IF NOT EXISTS idx_user_permissions_lookup ON user_permissions(user_id, permission_id);

-- 4. PASSWORD POLICY, HISTORY & SESSION CONTROL
ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS active_session_id VARCHAR(128) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS session_expires_at TIMESTAMP NULL;

-- Backward compatibility aliases for api_tokens
ALTER TABLE api_tokens ADD COLUMN IF NOT EXISTS token VARCHAR(255);
ALTER TABLE api_tokens ADD COLUMN IF NOT EXISTS name VARCHAR(100);

CREATE TABLE IF NOT EXISTS password_histories (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_password_histories_user ON password_histories(user_id, created_at DESC);

-- 5. WEBAUTHN / FIDO2 CREDENTIALS
CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    credential_id TEXT UNIQUE NOT NULL,
    public_key TEXT NOT NULL,
    attestation_type VARCHAR(50) DEFAULT 'none',
    sign_count BIGINT DEFAULT 0,
    transports JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_webauthn_credentials_user ON webauthn_credentials(user_id);

-- 6. SEED DEFAULT GXP PERMISSIONS
INSERT INTO permissions (module, action, code, description) VALUES
-- Samples
('samples', 'view', 'samples.view', 'View samples and sample details'),
('samples', 'create', 'samples.create', 'Register new laboratory samples'),
('samples', 'edit', 'samples.edit', 'Modify sample metadata before testing'),
('samples', 'delete', 'samples.delete', 'Soft delete samples with audit reason'),
('samples', 'assign', 'samples.assign', 'Assign tests, analysts, and methods to samples'),
('samples', 'approve', 'samples.approve', 'Final approval of samples for release'),
-- Test Results
('results', 'view', 'results.view', 'View test results'),
('results', 'enter', 'results.enter', 'Enter and submit raw or calculated test results'),
('results', 'review', 'results.review', 'Perform 4-eyes secondary technical review on results'),
('results', 'approve', 'results.approve', 'Approve test results for COA generation'),
-- COA
('coa', 'view', 'coa.view', 'View Certificate of Analysis documents'),
('coa', 'generate', 'coa.generate', 'Generate draft COA documents'),
('coa', 'release', 'coa.release', 'Sign and authorize official release of COA'),
-- Quality (OOS, CAPA, Deviations)
('quality', 'view', 'quality.view', 'View OOS, CAPA, and deviations'),
('quality', 'initiate', 'quality.initiate', 'Initiate quality investigations and deviations'),
('quality', 'close', 'quality.close', 'Approve and close CAPA/OOS investigations'),
-- Instruments & Methods
('instruments', 'view', 'instruments.view', 'View instruments and calibration status'),
('instruments', 'manage', 'instruments.manage', 'Configure instruments, methods, and watch folders'),
('instruments', 'calibrate', 'instruments.calibrate', 'Record and verify instrument calibrations'),
-- Audit & Compliance
('audit', 'view', 'audit.view', 'Inspect 21 CFR Part 11 audit trails'),
('audit', 'verify', 'audit.verify', 'Run cryptographic audit trail integrity verification'),
-- Administration & Identity
('admin', 'users', 'admin.users', 'Manage user accounts, roles, and credentials'),
('admin', 'permissions', 'admin.permissions', 'Configure the granular RBAC permission matrix'),
('admin', 'security', 'admin.security', 'Manage SSO, API tokens, and security policies')
ON CONFLICT (code) DO NOTHING;

-- Map permissions to roles
-- 1. Admin gets all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name IN ('Admin', 'Administrator')
ON CONFLICT (role_id, permission_id) DO NOTHING;

-- 2. Analyst
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'Analyst' AND p.code IN (
    'samples.view', 'samples.create', 'samples.assign',
    'results.view', 'results.enter',
    'coa.view', 'coa.generate',
    'quality.view', 'quality.initiate',
    'instruments.view', 'instruments.calibrate'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

-- 3. Reviewer
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'Reviewer' AND p.code IN (
    'samples.view',
    'results.view', 'results.review',
    'coa.view',
    'quality.view', 'quality.initiate',
    'instruments.view', 'audit.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

-- 4. Approver
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'Approver' AND p.code IN (
    'samples.view', 'samples.approve',
    'results.view', 'results.approve',
    'coa.view', 'coa.release',
    'quality.view', 'quality.close',
    'instruments.view', 'audit.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

-- 5. Customer (Client Portal)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'Customer' AND p.code IN (
    'samples.view', 'coa.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;
