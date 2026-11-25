-- ====================================================================
-- Rollback: rollback_022_enterprise_integration_identity.sql
-- Description: Rollback migration 022 Enterprise Integration & Identity
-- ====================================================================

DROP TABLE IF EXISTS fhir_resources CASCADE;

ALTER TABLE sso_providers DROP COLUMN IF EXISTS ldap_bind_password;
ALTER TABLE sso_providers DROP COLUMN IF EXISTS ldap_bind_dn;
ALTER TABLE sso_providers DROP COLUMN IF EXISTS ldap_base_dn;
ALTER TABLE sso_providers DROP COLUMN IF EXISTS ldap_port;
ALTER TABLE sso_providers DROP COLUMN IF EXISTS ldap_host;
ALTER TABLE sso_providers DROP COLUMN IF EXISTS certificate_x509;
ALTER TABLE sso_providers DROP COLUMN IF EXISTS idp_entity_id;

DROP TABLE IF EXISTS erp_connectors CASCADE;
