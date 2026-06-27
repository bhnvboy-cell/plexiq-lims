-- ============================================================
-- ADVANCED LIMS - PostgreSQL Schema
-- ============================================================

-- Required for PostgreSQL 15+ (public schema CREATE is revoked by default)
GRANT CREATE ON SCHEMA public TO PUBLIC;

BEGIN;

-- ============================================================
-- USERS & AUTHENTICATION
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role_id INTEGER REFERENCES roles(id),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_history (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_at TIMESTAMP,
    session_id VARCHAR(255)
);

-- ============================================================
-- MASTER DATA
-- ============================================================
CREATE TABLE IF NOT EXISTS customers (
    id SERIAL PRIMARY KEY,
    customer_code VARCHAR(50) UNIQUE NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    postal_code VARCHAR(20),
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    sap_id VARCHAR(50),
    last_synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    product_code VARCHAR(50) UNIQUE NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    sap_id VARCHAR(50),
    last_synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS methods (
    id SERIAL PRIMARY KEY,
    method_code VARCHAR(50) UNIQUE NOT NULL,
    method_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS units (
    id SERIAL PRIMARY KEY,
    unit_code VARCHAR(20) UNIQUE NOT NULL,
    unit_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tests (
    id SERIAL PRIMARY KEY,
    test_code VARCHAR(50) UNIQUE NOT NULL,
    test_name VARCHAR(255) NOT NULL,
    method_id INTEGER REFERENCES methods(id),
    unit_id INTEGER REFERENCES units(id),
    min_spec_limit NUMERIC(18,6),
    max_spec_limit NUMERIC(18,6),
    spec_limit_text VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    sap_id VARCHAR(50),
    last_synced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SAMPLE MANAGEMENT
-- ============================================================
CREATE TABLE IF NOT EXISTS samples (
    id SERIAL PRIMARY KEY,
    sample_code VARCHAR(50) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id),
    product_id INTEGER REFERENCES products(id),
    batch_number VARCHAR(100),
    batch_size VARCHAR(50),
    manufacture_date DATE,
    expiry_date DATE,
    received_date DATE NOT NULL DEFAULT CURRENT_DATE,
    target_completion_date DATE,
    priority VARCHAR(20) DEFAULT 'Normal' CHECK (priority IN ('Low', 'Normal', 'High', 'Urgent')),
    status VARCHAR(50) DEFAULT 'Registered' CHECK (status IN ('Registered','In Progress','Reviewed','Approved','COA Released','Rejected')),
    assigned_analyst_id INTEGER REFERENCES users(id),
    assigned_reviewer_id INTEGER REFERENCES users(id),
    assigned_approver_id INTEGER REFERENCES users(id),
    registered_by INTEGER REFERENCES users(id),
    reviewed_by INTEGER REFERENCES users(id),
    approved_by INTEGER REFERENCES users(id),
    reviewed_at TIMESTAMP,
    approved_at TIMESTAMP,
    coa_released_at TIMESTAMP,
    coa_released_by INTEGER REFERENCES users(id),
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    sap_sync_status VARCHAR(20) DEFAULT 'Pending' CHECK (sap_sync_status IN ('Pending','Synced','Failed')),
    sap_sync_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sample_tests (
    id SERIAL PRIMARY KEY,
    sample_id INTEGER REFERENCES samples(id) ON DELETE CASCADE,
    test_id INTEGER REFERENCES tests(id),
    assigned_to INTEGER REFERENCES users(id),
    status VARCHAR(50) DEFAULT 'Pending' CHECK (status IN ('Pending','In Progress','Completed','Reviewed','Approved','Rejected')),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    reviewed_at TIMESTAMP,
    approved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- RESULTS
-- ============================================================
CREATE TABLE IF NOT EXISTS results (
    id SERIAL PRIMARY KEY,
    sample_test_id INTEGER REFERENCES sample_tests(id) ON DELETE CASCADE,
    result_value NUMERIC(18,6),
    result_text TEXT,
    is_within_spec BOOLEAN,
    entered_by INTEGER REFERENCES users(id),
    entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INTEGER REFERENCES users(id),
    reviewed_at TIMESTAMP,
    approved_by INTEGER REFERENCES users(id),
    approved_at TIMESTAMP,
    remarks TEXT,
    revision INTEGER DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS result_revisions (
    id SERIAL PRIMARY KEY,
    result_id INTEGER REFERENCES results(id) ON DELETE CASCADE,
    revision INTEGER NOT NULL,
    result_value NUMERIC(18,6),
    result_text TEXT,
    changed_by INTEGER REFERENCES users(id),
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_reason TEXT
);

-- ============================================================
-- CERTIFICATE OF ANALYSIS (COA)
-- ============================================================
CREATE TABLE IF NOT EXISTS coa_templates (
    id SERIAL PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    template_html TEXT NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coa_documents (
    id SERIAL PRIMARY KEY,
    sample_id INTEGER REFERENCES samples(id) ON DELETE CASCADE,
    template_id INTEGER REFERENCES coa_templates(id),
    document_number VARCHAR(50) UNIQUE NOT NULL,
    pdf_path VARCHAR(500),
    generated_by INTEGER REFERENCES users(id),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP,
    released_by INTEGER REFERENCES users(id),
    status VARCHAR(20) DEFAULT 'Draft' CHECK (status IN ('Draft','Released','Revoked')),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- AUDIT TRAIL
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INTEGER,
    old_value JSONB,
    new_value JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SAP HANA SYNC
-- ============================================================
CREATE TABLE IF NOT EXISTS sap_sync_logs (
    id SERIAL PRIMARY KEY,
    sync_type VARCHAR(50) NOT NULL CHECK (sync_type IN ('Push','Pull')),
    entity_type VARCHAR(100) NOT NULL,
    entity_id INTEGER,
    status VARCHAR(20) DEFAULT 'Pending' CHECK (status IN ('Pending','In Progress','Success','Failed')),
    request_payload JSONB,
    response_payload JSONB,
    error_message TEXT,
    retry_count INTEGER DEFAULT 0,
    max_retries INTEGER DEFAULT 3,
    next_retry_at TIMESTAMP,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sap_config (
    id SERIAL PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT NOT NULL,
    is_encrypted BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- INSTRUMENT INTEGRATION
-- ============================================================
CREATE TABLE IF NOT EXISTS instruments (
    id SERIAL PRIMARY KEY,
    instrument_code VARCHAR(50) UNIQUE NOT NULL,
    instrument_name VARCHAR(255) NOT NULL,
    model VARCHAR(255),
    manufacturer VARCHAR(255),
    interface_type VARCHAR(20) NOT NULL CHECK (interface_type IN ('XML','CSV','TEXT')),
    parser_config JSONB DEFAULT '{}',
    host VARCHAR(255),
    port INTEGER,
    file_watch_path VARCHAR(500),
    auto_import BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS instrument_results (
    id SERIAL PRIMARY KEY,
    instrument_id INTEGER REFERENCES instruments(id) ON DELETE CASCADE,
    sample_test_id INTEGER REFERENCES sample_tests(id) ON DELETE SET NULL,
    sample_code VARCHAR(50),
    test_code VARCHAR(50),
    raw_data TEXT,
    parsed_data JSONB,
    result_value NUMERIC(18,6),
    result_text TEXT,
    unit VARCHAR(50),
    status VARCHAR(20) DEFAULT 'Pending' CHECK (status IN ('Pending','Matched','Imported','Failed')),
    imported_by INTEGER REFERENCES users(id),
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_active ON users(is_active);

CREATE INDEX idx_samples_code ON samples(sample_code);
CREATE INDEX idx_samples_customer ON samples(customer_id);
CREATE INDEX idx_samples_product ON samples(product_id);
CREATE INDEX idx_samples_status ON samples(status);
CREATE INDEX idx_samples_priority ON samples(priority);
CREATE INDEX idx_samples_analyst ON samples(assigned_analyst_id);
CREATE INDEX idx_samples_batch ON samples(batch_number);
CREATE INDEX idx_samples_received_date ON samples(received_date);
CREATE INDEX idx_samples_sap_sync ON samples(sap_sync_status);
CREATE INDEX idx_samples_registered_by ON samples(registered_by);

CREATE INDEX idx_sample_tests_sample ON sample_tests(sample_id);
CREATE INDEX idx_sample_tests_test ON sample_tests(test_id);
CREATE INDEX idx_sample_tests_status ON sample_tests(status);
CREATE INDEX idx_sample_tests_assigned ON sample_tests(assigned_to);

CREATE INDEX idx_results_sample_test ON results(sample_test_id);
CREATE INDEX idx_results_entered_by ON results(entered_by);

CREATE INDEX idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_action ON audit_logs(action);
CREATE INDEX idx_audit_logs_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);

CREATE INDEX idx_sap_sync_logs_type ON sap_sync_logs(sync_type, entity_type);
CREATE INDEX idx_sap_sync_logs_status ON sap_sync_logs(status);
CREATE INDEX idx_sap_sync_logs_created ON sap_sync_logs(created_at);

CREATE INDEX idx_coa_documents_sample ON coa_documents(sample_id);
CREATE INDEX idx_coa_documents_status ON coa_documents(status);

CREATE INDEX idx_login_history_user ON login_history(user_id);
CREATE INDEX idx_login_history_login ON login_history(login_at);

CREATE INDEX idx_instruments_code ON instruments(instrument_code);
CREATE INDEX idx_instruments_active ON instruments(is_active);

CREATE INDEX idx_inst_results_instrument ON instrument_results(instrument_id);
CREATE INDEX idx_inst_results_sample_test ON instrument_results(sample_test_id);
CREATE INDEX idx_inst_results_sample_code ON instrument_results(sample_code);
CREATE INDEX idx_inst_results_status ON instrument_results(status);

-- ============================================================
-- SEQUENCE FOR AUTO-GENERATING SAMPLE CODES
-- ============================================================
CREATE SEQUENCE IF NOT EXISTS sample_code_seq START 1000 INCREMENT 1;

-- ============================================================
-- SEED DATA: ROLES
-- ============================================================
INSERT INTO roles (name, description) VALUES
    ('Admin', 'System Administrator – full access'),
    ('Analyst', 'Laboratory Analyst – enters results'),
    ('Reviewer', 'Reviews results before approval'),
    ('Approver', 'Final sign-off on results'),
    ('Customer', 'External customer/broker – views COA only')
ON CONFLICT (name) DO NOTHING;

-- ============================================================
-- SEED DATA: DEFAULT ADMIN USER (password: admin@123)
-- ============================================================
INSERT INTO users (username, email, password_hash, full_name, role_id, is_active)
SELECT 'admin', 'admin@lims.local',
       '$2y$10$NmwEp0uQ4qw8EgRCB2ufO.eFOrW.adgPgw6vDskyboJpmuj9k2KUq',
       'System Administrator',
       (SELECT id FROM roles WHERE name = 'Admin'),
       TRUE
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

INSERT INTO users (username, email, password_hash, full_name, role_id, is_active)
SELECT 'analyst', 'analyst@lims.local',
       '$2y$10$NmwEp0uQ4qw8EgRCB2ufO.eFOrW.adgPgw6vDskyboJpmuj9k2KUq',
       'Lab Analyst',
       (SELECT id FROM roles WHERE name = 'Analyst'),
       TRUE
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'analyst');

INSERT INTO users (username, email, password_hash, full_name, role_id, is_active)
SELECT 'reviewer', 'reviewer@lims.local',
       '$2y$10$NmwEp0uQ4qw8EgRCB2ufO.eFOrW.adgPgw6vDskyboJpmuj9k2KUq',
       'Quality Reviewer',
       (SELECT id FROM roles WHERE name = 'Reviewer'),
       TRUE
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'reviewer');

INSERT INTO users (username, email, password_hash, full_name, role_id, is_active)
SELECT 'approver', 'approver@lims.local',
       '$2y$10$NmwEp0uQ4qw8EgRCB2ufO.eFOrW.adgPgw6vDskyboJpmuj9k2KUq',
       'Final Approver',
       (SELECT id FROM roles WHERE name = 'Approver'),
       TRUE
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'approver');

INSERT INTO users (username, email, password_hash, full_name, role_id, is_active)
SELECT 'customer', 'customer@lims.local',
       '$2y$10$NmwEp0uQ4qw8EgRCB2ufO.eFOrW.adgPgw6vDskyboJpmuj9k2KUq',
       'External Customer',
       (SELECT id FROM roles WHERE name = 'Customer'),
       TRUE
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'customer');

-- ============================================================
-- SEED DATA: METHODS
-- ============================================================
INSERT INTO methods (method_code, method_name, description) VALUES
    ('HPLC-001', 'High Performance Liquid Chromatography', 'HPLC analysis method'),
    ('GC-001', 'Gas Chromatography', 'GC analysis method'),
    ('FTIR-001', 'Fourier Transform Infrared Spectroscopy', 'FTIR method'),
    ('TITR-001', 'Titration', 'Wet chemistry titration'),
    ('PH-001', 'pH Measurement', 'pH meter method'),
    ('GRAV-001', 'Gravimetric Analysis', 'Gravimetric determination')
ON CONFLICT (method_code) DO NOTHING;

-- ============================================================
-- SEED DATA: UNITS
-- ============================================================
INSERT INTO units (unit_code, unit_name) VALUES
    ('%', 'Percentage'),
    ('ppm', 'Parts Per Million'),
    ('ppb', 'Parts Per Billion'),
    ('mg/L', 'Milligrams per Liter'),
    ('g/L', 'Grams per Liter'),
    ('mg/kg', 'Milligrams per Kilogram'),
    ('pH', 'pH Units'),
    ('mm', 'Millimeters'),
    ('cP', 'Centipoise'),
    ('N', 'Normality')
ON CONFLICT (unit_code) DO NOTHING;

-- ============================================================
-- SEED DATA: TESTS
-- ============================================================
INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text) VALUES
    ('TEST-001', 'Assay by HPLC', (SELECT id FROM methods WHERE method_code = 'HPLC-001'), (SELECT id FROM units WHERE unit_code = '%'), 98.0, 102.0, '98.0% - 102.0%'),
    ('TEST-002', 'Related Substances', (SELECT id FROM methods WHERE method_code = 'HPLC-001'), (SELECT id FROM units WHERE unit_code = '%'), 0, 1.5, 'NMT 1.5%'),
    ('TEST-003', 'pH', (SELECT id FROM methods WHERE method_code = 'PH-001'), (SELECT id FROM units WHERE unit_code = 'pH'), 5.5, 7.5, '5.5 - 7.5'),
    ('TEST-004', 'Residual Solvents', (SELECT id FROM methods WHERE method_code = 'GC-001'), (SELECT id FROM units WHERE unit_code = 'ppm'), 0, 5000, 'NMT 5000 ppm'),
    ('TEST-005', 'Heavy Metals', (SELECT id FROM methods WHERE method_code = 'TITR-001'), (SELECT id FROM units WHERE unit_code = 'ppm'), 0, 20, 'NMT 20 ppm'),
    ('TEST-006', 'Loss on Drying', (SELECT id FROM methods WHERE method_code = 'GRAV-001'), (SELECT id FROM units WHERE unit_code = '%'), 0, 5.0, 'NMT 5.0%'),
    ('TEST-007', 'Identification by FTIR', (SELECT id FROM methods WHERE method_code = 'FTIR-001'), (SELECT id FROM units WHERE unit_code = '%'), 0, 0, 'Conforms/Does Not Conform'),
    ('TEST-008', 'Viscosity', (SELECT id FROM methods WHERE method_code = 'HPLC-001'), (SELECT id FROM units WHERE unit_code = 'cP'), 100, 500, '100 - 500 cP')
ON CONFLICT (test_code) DO NOTHING;

-- ============================================================
-- SEED DATA: DEFAULT COA TEMPLATE
-- ============================================================
INSERT INTO coa_templates (template_name, template_html, is_default, is_active)
SELECT 'Standard COA', '<html><head><style>
body { font-family: Arial, sans-serif; font-size: 12px; }
.header { text-align: center; margin-bottom: 20px; }
.header h1 { font-size: 18px; margin: 0; }
.header h2 { font-size: 14px; color: #666; margin: 5px 0; }
.watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0.1; font-size: 80px; transform: rotate(-30deg); }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #333; padding: 6px; text-align: left; }
th { background-color: #e0e0e0; }
.footer { margin-top: 30px; font-size: 10px; text-align: center; color: #999; }
.signature { margin-top: 20px; }
.signature td { border: none; padding: 5px; }
</style></head><body>
<div class="watermark">[[COMPANY_NAME]]</div>
<div class="header">
<h1>CERTIFICATE OF ANALYSIS</h1>
<h2>[[COMPANY_NAME]]</h2>
<p>[[COMPANY_ADDRESS]]</p>
</div>
<table>
<tr><td><strong>Certificate No:</strong></td><td>[[COA_NUMBER]]</td></tr>
<tr><td><strong>Sample Code:</strong></td><td>[[SAMPLE_CODE]]</td></tr>
<tr><td><strong>Customer:</strong></td><td>[[CUSTOMER_NAME]]</td></tr>
<tr><td><strong>Product:</strong></td><td>[[PRODUCT_NAME]]</td></tr>
<tr><td><strong>Batch Number:</strong></td><td>[[BATCH_NUMBER]]</td></tr>
<tr><td><strong>Manufacture Date:</strong></td><td>[[MANUFACTURE_DATE]]</td></tr>
<tr><td><strong>Expiry Date:</strong></td><td>[[EXPIRY_DATE]]</td></tr>
<tr><td><strong>Date of Analysis:</strong></td><td>[[ANALYSIS_DATE]]</td></tr>
<tr><td><strong>Status:</strong></td><td>[[STATUS]]</td></tr>
</table>
<h3>Analysis Results</h3>
<table>
<tr><th>Test Name</th><th>Method</th><th>Specification</th><th>Result</th><th>Unit</th><th>Status</th></tr>
[[RESULTS_ROWS]]
</table>
<div class="signature">
<table>
<tr><td><strong>Reviewed by:</strong></td><td>[[REVIEWED_BY]]</td><td>Date: [[REVIEWED_DATE]]</td></tr>
<tr><td><strong>Approved by:</strong></td><td>[[APPROVED_BY]]</td><td>Date: [[APPROVED_DATE]]</td></tr>
</table>
</div>
<div class="footer">
<p>This Certificate of Analysis is electronically generated and is valid without signature.</p>
<p>[[COMPANY_NAME]] | [[COMPANY_ADDRESS]] | [[COMPANY_PHONE]] | [[COMPANY_EMAIL]]</p>
</div>
</body></html>', TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM coa_templates WHERE is_default = TRUE);

-- ============================================================
-- PROJECT MANAGEMENT
-- ============================================================
CREATE TABLE IF NOT EXISTS projects (
    id SERIAL PRIMARY KEY,
    project_code VARCHAR(50) UNIQUE NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'Active' CHECK (status IN ('Active','Completed','On Hold','Cancelled')),
    priority VARCHAR(20) DEFAULT 'Medium' CHECK (priority IN ('Low','Medium','High','Critical')),
    start_date DATE,
    target_end_date DATE,
    actual_end_date DATE,
    manager_id INTEGER REFERENCES users(id),
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS project_samples (
    id SERIAL PRIMARY KEY,
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    sample_id INTEGER REFERENCES samples(id) ON DELETE CASCADE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, sample_id)
);

-- ============================================================
-- OOS (OUT OF SPECIFICATION)
-- ============================================================
CREATE TABLE IF NOT EXISTS oos_records (
    id SERIAL PRIMARY KEY,
    oos_number VARCHAR(50) UNIQUE NOT NULL,
    sample_id INTEGER REFERENCES samples(id) ON DELETE SET NULL,
    sample_test_id INTEGER REFERENCES sample_tests(id) ON DELETE SET NULL,
    result_id INTEGER REFERENCES results(id) ON DELETE SET NULL,
    test_parameter VARCHAR(255),
    specification_range VARCHAR(255),
    result_value NUMERIC(18,6),
    result_text TEXT,
    unit VARCHAR(50),
    description TEXT NOT NULL,
    severity VARCHAR(20) DEFAULT 'Minor' CHECK (severity IN ('Minor','Major','Critical')),
    status VARCHAR(20) DEFAULT 'Open' CHECK (status IN ('Open','Under Investigation','Closed')),
    disposition VARCHAR(20) CHECK (disposition IN ('Approved','Rejected','Rerun','Retest')),
    disposition_notes TEXT,
    initiated_by INTEGER REFERENCES users(id) NOT NULL,
    assigned_to INTEGER REFERENCES users(id),
    closed_by INTEGER REFERENCES users(id),
    closed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS oos_investigations (
    id SERIAL PRIMARY KEY,
    oos_id INTEGER REFERENCES oos_records(id) ON DELETE CASCADE,
    root_cause TEXT,
    immediate_action TEXT,
    corrective_action TEXT,
    preventive_action TEXT,
    investigation_notes TEXT,
    investigated_by INTEGER REFERENCES users(id),
    investigated_at TIMESTAMP,
    reviewed_by INTEGER REFERENCES users(id),
    reviewed_at TIMESTAMP,
    review_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- CAPA (CORRECTIVE & PREVENTIVE ACTION)
-- ============================================================
CREATE TABLE IF NOT EXISTS capa_records (
    id SERIAL PRIMARY KEY,
    capa_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    source_type VARCHAR(30) CHECK (source_type IN ('OOS','Audit','Customer Complaint','Deviation','Other')),
    source_reference_id INTEGER,
    source_reference_type VARCHAR(50),
    root_cause TEXT,
    corrective_action_plan TEXT,
    preventive_action_plan TEXT,
    effectiveness_check TEXT,
    effectiveness_results TEXT,
    priority VARCHAR(20) DEFAULT 'Medium' CHECK (priority IN ('Low','Medium','High','Critical')),
    status VARCHAR(20) DEFAULT 'Open' CHECK (status IN ('Open','In Progress','Under Review','Completed','Closed')),
    due_date DATE,
    completed_date DATE,
    assigned_to INTEGER REFERENCES users(id),
    created_by INTEGER REFERENCES users(id) NOT NULL,
    reviewed_by INTEGER REFERENCES users(id),
    closed_by INTEGER REFERENCES users(id),
    closed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- INDEXES FOR NEW MODULES
CREATE INDEX IF NOT EXISTS idx_projects_code ON projects(project_code);
CREATE INDEX IF NOT EXISTS idx_projects_status ON projects(status);
CREATE INDEX IF NOT EXISTS idx_project_samples_project ON project_samples(project_id);
CREATE INDEX IF NOT EXISTS idx_project_samples_sample ON project_samples(sample_id);
CREATE INDEX IF NOT EXISTS idx_oos_number ON oos_records(oos_number);
CREATE INDEX IF NOT EXISTS idx_oos_status ON oos_records(status);
CREATE INDEX IF NOT EXISTS idx_oos_severity ON oos_records(severity);
CREATE INDEX IF NOT EXISTS idx_oos_initiated ON oos_records(initiated_by);
CREATE INDEX IF NOT EXISTS idx_oos_sample ON oos_records(sample_id);
CREATE INDEX IF NOT EXISTS idx_oos_investigation_oos ON oos_investigations(oos_id);
CREATE INDEX IF NOT EXISTS idx_capa_number ON capa_records(capa_number);
CREATE INDEX IF NOT EXISTS idx_capa_status ON capa_records(status);
CREATE INDEX IF NOT EXISTS idx_capa_source ON capa_records(source_type);
CREATE INDEX IF NOT EXISTS idx_capa_assigned ON capa_records(assigned_to);

-- ============================================================
-- SEED DATA: SAP CONFIG DEFAULTS
-- ============================================================
INSERT INTO sap_config (config_key, config_value, is_encrypted) VALUES
    ('sap_hana_host', 'localhost', FALSE),
    ('sap_hana_port', '30015', FALSE),
    ('sap_hana_username', 'SYSTEM', FALSE),
    ('sap_hana_password', '', TRUE),
    ('sap_odata_url', 'http://localhost:8000/sap/opu/odata/', FALSE),
    ('sap_sync_enabled', 'false', FALSE),
    ('sap_sync_interval_minutes', '5', FALSE),
    ('sap_last_sync_at', '', FALSE)
ON CONFLICT (config_key) DO NOTHING;

-- ============================================================
-- SEED DATA: SAMPLE CUSTOMER (FOR TESTING)
-- ============================================================
INSERT INTO customers (customer_code, customer_name, address, city, country, contact_person, email, phone, is_active)
SELECT 'CUST-001', 'Test Customer Inc.', '123 Main Street', 'New York', 'USA', 'John Doe', 'john@testcustomer.com', '+1-555-0100', TRUE
WHERE NOT EXISTS (SELECT 1 FROM customers WHERE customer_code = 'CUST-001');

-- ============================================================
-- SEED DATA: SAMPLE PRODUCT (FOR TESTING)
-- ============================================================
INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'PROD-001', 'Test Product Alpha', 'A test product for demonstration', 'Chemicals', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'PROD-001');

-- ============================================================
-- SEED DATA: DEMO PROJECT
-- ============================================================
INSERT INTO projects (project_code, project_name, description, status, priority, start_date, target_end_date, created_by)
SELECT 'PRJ-001', 'Q4 Quality Improvement', 'Company-wide quality improvement initiative for Q4', 'Active', 'High', CURRENT_DATE, CURRENT_DATE + INTERVAL '90 days', 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE project_code = 'PRJ-001');

-- ============================================================
-- SEED DATA: DEMO OOS RECORD
-- ============================================================
INSERT INTO oos_records (oos_number, test_parameter, specification_range, description, severity, status, initiated_by)
SELECT 'OOS-001', 'pH Level', '6.5 - 7.5', 'pH reading exceeded upper specification limit during routine testing', 'Major', 'Open', (SELECT id FROM users WHERE username = 'analyst')
WHERE NOT EXISTS (SELECT 1 FROM oos_records WHERE oos_number = 'OOS-001');

-- ============================================================
-- SEED DATA: DEMO CAPA RECORD
-- ============================================================
INSERT INTO capa_records (capa_number, title, description, source_type, priority, status, created_by, assigned_to, due_date)
SELECT 'CAPA-001', 'pH Meter Calibration Issue', 'Investigate and resolve pH meter calibration drift identified during OOS investigation', 'OOS', 'High', 'Open', (SELECT id FROM users WHERE username = 'admin'), (SELECT id FROM users WHERE username = 'analyst'), CURRENT_DATE + INTERVAL '30 days'
WHERE NOT EXISTS (SELECT 1 FROM capa_records WHERE capa_number = 'CAPA-001');

-- ============================================================
-- SPC: CONTROL CHARTS & PROCESS CONTROL
-- ============================================================
CREATE TABLE IF NOT EXISTS spc_parameters (
    id SERIAL PRIMARY KEY,
    parameter_code VARCHAR(50) UNIQUE NOT NULL,
    parameter_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    unit VARCHAR(50),
    spec_min NUMERIC(12,4),
    spec_max NUMERIC(12,4),
    spec_target NUMERIC(12,4),
    ucl NUMERIC(12,4),
    lcl NUMERIC(12,4),
    subgroup_size INTEGER DEFAULT 1,
    calculation_formula TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS spc_readings (
    id SERIAL PRIMARY KEY,
    parameter_id INTEGER REFERENCES spc_parameters(id) ON DELETE CASCADE,
    batch_id VARCHAR(100),
    reading_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    value NUMERIC(12,4) NOT NULL,
    entered_by INTEGER REFERENCES users(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_spc_readings_param_date ON spc_readings(parameter_id, reading_date);
CREATE INDEX IF NOT EXISTS idx_spc_readings_batch ON spc_readings(batch_id);

-- ============================================================
-- SEED DATA: SPC PARAMETERS (Starch & Glucose Industry)
-- ============================================================
INSERT INTO spc_parameters (parameter_code, parameter_name, category, unit, spec_min, spec_max, spec_target, ucl, lcl, subgroup_size) VALUES
    ('PH-001', 'pH Level', 'Chemical', 'pH', 4.5, 5.5, 5.0, 5.7, 4.3, 1),
    ('DS-001', 'Dry Substance', 'Concentration', '% w/w', 68.0, 72.0, 70.0, 73.0, 67.0, 1),
    ('DP1-001', 'DP1 (Glucose)', 'Carbohydrate Profile', '%', 90.0, 97.0, 93.5, 98.0, 89.0, 1),
    ('DP2-001', 'DP2 (Maltose)', 'Carbohydrate Profile', '%', 2.0, 6.0, 4.0, 7.0, 1.0, 1),
    ('DP3-001', 'DP3 (Maltotriose)', 'Carbohydrate Profile', '%', 0.5, 3.0, 1.5, 4.0, 0.0, 1),
    ('DP4-001', 'DP4+ (Higher Saccharides)', 'Carbohydrate Profile', '%', 0.5, 4.0, 2.0, 5.0, 0.0, 1),
    ('DE-001', 'Dextrose Equivalent', 'Calculated', 'DE', 95.0, 99.5, 97.5, 99.8, 94.0, 1),
    ('VISC-001', 'Viscosity', 'Physical', 'mPa·s', 10.0, 30.0, 20.0, 35.0, 5.0, 1),
    ('COL-001', 'Color (ICUMSA)', 'Physical', 'IU', 0.0, 50.0, 25.0, 60.0, 0.0, 1),
    ('TURB-001', 'Turbidity', 'Physical', 'NTU', 0.0, 10.0, 5.0, 12.0, 0.0, 1)
ON CONFLICT (parameter_code) DO NOTHING;

-- ============================================================
-- SEED DATA: SPC READINGS (30 days of demo data)
-- ============================================================
DO $$
DECLARE
    pid INTEGER;
    i INTEGER;
    d DATE;
    val NUMERIC;
    r NUMERIC;
BEGIN
    -- pH readings (target 5.0, sigma ~0.15)
    SELECT id INTO pid FROM spc_parameters WHERE parameter_code = 'PH-001';
    FOR i IN 1..60 LOOP
        d := CURRENT_DATE - (60 - i) * INTERVAL '12 hours';
        val := 5.0 + (random() - 0.5) * 0.6;
        INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, notes)
        VALUES (pid, 'BATCH-' || LPAD(i::TEXT, 4, '0'), d, val, 'Routine QC check');
    END LOOP;

    -- DS readings (target 70.0, sigma ~0.8)
    SELECT id INTO pid FROM spc_parameters WHERE parameter_code = 'DS-001';
    FOR i IN 1..60 LOOP
        d := CURRENT_DATE - (60 - i) * INTERVAL '12 hours';
        val := 70.0 + (random() - 0.5) * 3.0;
        INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, notes)
        VALUES (pid, 'BATCH-' || LPAD(i::TEXT, 4, '0'), d, val, 'Evaporation stage');
    END LOOP;

    -- DP1 readings (target 93.5)
    SELECT id INTO pid FROM spc_parameters WHERE parameter_code = 'DP1-001';
    FOR i IN 1..60 LOOP
        d := CURRENT_DATE - (60 - i) * INTERVAL '12 hours';
        val := 93.5 + (random() - 0.5) * 4.0;
        INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, notes)
        VALUES (pid, 'BATCH-' || LPAD(i::TEXT, 4, '0'), d, val, 'Ion exchange outlet');
    END LOOP;

    -- DP2 readings (target 4.0)
    SELECT id INTO pid FROM spc_parameters WHERE parameter_code = 'DP2-001';
    FOR i IN 1..60 LOOP
        d := CURRENT_DATE - (60 - i) * INTERVAL '12 hours';
        val := 4.0 + (random() - 0.5) * 2.0;
        INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, notes)
        VALUES (pid, 'BATCH-' || LPAD(i::TEXT, 4, '0'), d, val, '');
    END LOOP;

    -- DP3 readings (target 1.5)
    SELECT id INTO pid FROM spc_parameters WHERE parameter_code = 'DP3-001';
    FOR i IN 1..60 LOOP
        d := CURRENT_DATE - (60 - i) * INTERVAL '12 hours';
        val := 1.5 + (random() - 0.5) * 1.5;
        INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, notes)
        VALUES (pid, 'BATCH-' || LPAD(i::TEXT, 4, '0'), d, val, '');
    END LOOP;

    -- DP4+ readings (target 2.0)
    SELECT id INTO pid FROM spc_parameters WHERE parameter_code = 'DP4-001';
    FOR i IN 1..60 LOOP
        d := CURRENT_DATE - (60 - i) * INTERVAL '12 hours';
        val := 2.0 + (random() - 0.5) * 2.5;
        INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, notes)
        VALUES (pid, 'BATCH-' || LPAD(i::TEXT, 4, '0'), d, val, '');
    END LOOP;

    -- DE readings (target 97.5, calculated - demo data)
    SELECT id INTO pid FROM spc_parameters WHERE parameter_code = 'DE-001';
    FOR i IN 1..60 LOOP
        d := CURRENT_DATE - (60 - i) * INTERVAL '12 hours';
        val := 97.5 + (random() - 0.5) * 2.0;
        INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, notes)
        VALUES (pid, 'BATCH-' || LPAD(i::TEXT, 4, '0'), d, val, 'Calculated from DP profile');
    END LOOP;

    -- Viscosity readings (target 20.0)
    SELECT id INTO pid FROM spc_parameters WHERE parameter_code = 'VISC-001';
    FOR i IN 1..60 LOOP
        d := CURRENT_DATE - (60 - i) * INTERVAL '12 hours';
        val := 20.0 + (random() - 0.5) * 12.0;
        INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, notes)
        VALUES (pid, 'BATCH-' || LPAD(i::TEXT, 4, '0'), d, val, '60°C measurement');
    END LOOP;

    -- Color readings (target 25.0)
    SELECT id INTO pid FROM spc_parameters WHERE parameter_code = 'COL-001';
    FOR i IN 1..60 LOOP
        d := CURRENT_DATE - (60 - i) * INTERVAL '12 hours';
        val := GREATEST(0.1, 25.0 + (random() - 0.5) * 30.0);
        INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, notes)
        VALUES (pid, 'BATCH-' || LPAD(i::TEXT, 4, '0'), d, val, 'ICUMSA method');
    END LOOP;

    -- Turbidity readings (target 5.0)
    SELECT id INTO pid FROM spc_parameters WHERE parameter_code = 'TURB-001';
    FOR i IN 1..60 LOOP
        d := CURRENT_DATE - (60 - i) * INTERVAL '12 hours';
        val := GREATEST(0.1, 5.0 + (random() - 0.5) * 5.0);
        INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, notes)
        VALUES (pid, 'BATCH-' || LPAD(i::TEXT, 4, '0'), d, val, 'Post-filtration');
    END LOOP;
END $$;

COMMIT;
