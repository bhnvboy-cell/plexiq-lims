-- ====================================================================
-- Rollback Migration: rollback_018_foundation_hardening.sql
-- Description: Revert changes from 018_foundation_hardening.sql
-- ====================================================================

-- Revert permissions tables
DROP TABLE IF EXISTS user_permissions CASCADE;
DROP TABLE IF EXISTS role_permissions CASCADE;
DROP TABLE IF EXISTS permissions CASCADE;

-- Revert WebAuthn
DROP TABLE IF EXISTS webauthn_credentials CASCADE;

-- Revert password history & user columns
DROP TABLE IF EXISTS password_histories CASCADE;
ALTER TABLE users DROP COLUMN IF EXISTS must_change_password;
ALTER TABLE users DROP COLUMN IF EXISTS active_session_id;
ALTER TABLE users DROP COLUMN IF EXISTS session_expires_at;

-- Revert audit verifications table
DROP TABLE IF EXISTS audit_verifications CASCADE;

-- Restore prior trigger with update exception bypass
CREATE OR REPLACE FUNCTION block_audit_mutation() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'UPDATE' THEN
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
