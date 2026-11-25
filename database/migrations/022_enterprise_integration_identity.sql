-- ====================================================================
-- Migration: 022_enterprise_integration_identity.sql
-- Description: Phase 5 Enterprise Integration & Identity
--   1. Enterprise ERP Connectors (SAP HANA, Oracle Fusion, MS Dynamics 365)
--   2. SAML 2.0 & Active Directory / LDAP Identity Configurations
--   3. Clinical Pack: HL7 FHIR R4 Resources Storage & Interoperability
-- ====================================================================

-- 1. ENTERPRISE ERP CONNECTORS (SAP, Oracle Cloud, Dynamics 365)
CREATE TABLE IF NOT EXISTS erp_connectors (
    id SERIAL PRIMARY KEY,
    erp_type VARCHAR(30) NOT NULL CHECK (erp_type IN ('sap_hana', 'oracle_cloud', 'dynamics_365')),
    name VARCHAR(100) NOT NULL,
    base_url VARCHAR(255) NOT NULL,
    auth_type VARCHAR(30) NOT NULL CHECK (auth_type IN ('basic', 'oauth2_client_credentials', 'api_key', 'hmac')),
    auth_credentials JSONB NOT NULL DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    last_synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_erp_connectors_type ON erp_connectors(erp_type);

-- Seed default SAP HANA connector record
INSERT INTO erp_connectors (erp_type, name, base_url, auth_type, auth_credentials, is_active)
VALUES (
    'sap_hana',
    'SAP S/4HANA Quality Management (QM)',
    'https://sap-gateway.internal:44300/sap/opu/odata/sap',
    'oauth2_client_credentials',
    '{"client_id": "LIMS_SAP_CLIENT", "token_url": "https://sap-oauth.internal/oauth/token"}',
    TRUE
) ON CONFLICT DO NOTHING;

-- 2. ENTERPRISE IDENTITY (SAML 2.0 & LDAP / Active Directory)
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS idp_entity_id VARCHAR(500);
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS certificate_x509 TEXT;
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS ldap_host VARCHAR(255);
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS ldap_port INTEGER DEFAULT 389;
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS ldap_base_dn VARCHAR(255);
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS ldap_bind_dn VARCHAR(255);
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS ldap_bind_password TEXT;

-- 3. CLINICAL PACK: HL7 FHIR R4 RESOURCES
CREATE TABLE IF NOT EXISTS fhir_resources (
    id SERIAL PRIMARY KEY,
    resource_type VARCHAR(50) NOT NULL CHECK (resource_type IN ('Observation', 'DiagnosticReport', 'ServiceRequest', 'Patient', 'Specimen')),
    resource_id VARCHAR(100) UNIQUE NOT NULL,
    sample_id INTEGER REFERENCES samples(id) ON DELETE CASCADE,
    sample_test_id INTEGER REFERENCES sample_tests(id) ON DELETE CASCADE,
    payload JSONB NOT NULL,
    fhir_version VARCHAR(10) DEFAULT 'R4',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_fhir_type_id ON fhir_resources(resource_type, resource_id);
CREATE INDEX IF NOT EXISTS idx_fhir_sample ON fhir_resources(sample_id);
