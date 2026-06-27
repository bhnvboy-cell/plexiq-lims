-- ============================================================
-- Migration 008: Missing Features (all 20 items)
-- ============================================================

-- 1. ELN (Electronic Lab Notebook)
CREATE TABLE IF NOT EXISTS eln_notebooks (
    id SERIAL PRIMARY KEY,
    notebook_code VARCHAR(50) UNIQUE NOT NULL,
    notebook_name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    owner_id INTEGER REFERENCES users(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS eln_entries (
    id SERIAL PRIMARY KEY,
    entry_code VARCHAR(50) UNIQUE NOT NULL,
    notebook_id INTEGER REFERENCES eln_notebooks(id) ON DELETE CASCADE,
    title VARCHAR(500) NOT NULL,
    content TEXT,
    entry_type VARCHAR(50) DEFAULT 'General',
    status VARCHAR(50) DEFAULT 'Draft',
    created_by INTEGER REFERENCES users(id),
    reviewed_by INTEGER REFERENCES users(id),
    reviewed_at TIMESTAMP,
    tags TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS eln_entry_attachments (
    id SERIAL PRIMARY KEY,
    entry_id INTEGER REFERENCES eln_entries(id) ON DELETE CASCADE,
    filename VARCHAR(500) NOT NULL,
    original_name VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Billing & Invoicing
CREATE TABLE IF NOT EXISTS invoices (
    id SERIAL PRIMARY KEY,
    invoice_number VARCHAR(100) UNIQUE NOT NULL,
    customer_id INTEGER REFERENCES customers(id),
    sample_id INTEGER REFERENCES samples(id),
    invoice_date DATE DEFAULT CURRENT_DATE,
    due_date DATE,
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Draft',
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER REFERENCES invoices(id) ON DELETE CASCADE,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) DEFAULT 0,
    total_price DECIMAL(12,2) DEFAULT 0,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payments (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER REFERENCES invoices(id) ON DELETE CASCADE,
    payment_date DATE DEFAULT CURRENT_DATE,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(100),
    reference_number VARCHAR(255),
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Cloud / SaaS Deployment Config
CREATE TABLE IF NOT EXISTS deployment_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    category VARCHAR(100) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Mobile API - uses api_tokens from feature #9

-- 5. Barcode / RFID Scanning
CREATE TABLE IF NOT EXISTS barcode_scan_logs (
    id SERIAL PRIMARY KEY,
    barcode_value VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INTEGER,
    scanner_id VARCHAR(255),
    location VARCHAR(255),
    scanned_by INTEGER REFERENCES users(id),
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. 21 CFR Part 11 Electronic Signatures
CREATE TABLE IF NOT EXISTS electronic_signatures (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    action_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INTEGER,
    signature_hash VARCHAR(500) NOT NULL,
    signed_data JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. GDPR / HIPAA Compliance
CREATE TABLE IF NOT EXISTS data_consent_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    consent_type VARCHAR(100) NOT NULL,
    consent_given BOOLEAN DEFAULT TRUE,
    consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    notes TEXT
);

CREATE TABLE IF NOT EXISTS data_retention_policies (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(100) NOT NULL,
    retention_days INTEGER NOT NULL,
    action_on_expiry VARCHAR(100) DEFAULT 'Archive',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS data_privacy_logs (
    id SERIAL PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INTEGER,
    user_id INTEGER REFERENCES users(id),
    details JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. Role-Based Dashboard Customization
CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    widget_type VARCHAR(100) NOT NULL,
    widget_config JSONB,
    position_x INTEGER DEFAULT 0,
    position_y INTEGER DEFAULT 0,
    width INTEGER DEFAULT 4,
    height INTEGER DEFAULT 2,
    is_visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS saved_filters (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    filter_name VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    filter_config JSONB NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. REST API
CREATE TABLE IF NOT EXISTS api_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    token_hash VARCHAR(255) NOT NULL,
    token_name VARCHAR(255),
    permissions JSONB DEFAULT '[]',
    last_used_at TIMESTAMP,
    expires_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_webhooks (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(1000) NOT NULL,
    events JSONB NOT NULL,
    secret_key VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_triggered_at TIMESTAMP,
    failure_count INTEGER DEFAULT 0,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_rate_limits (
    id SERIAL PRIMARY KEY,
    api_token_id INTEGER REFERENCES api_tokens(id) ON DELETE CASCADE,
    requests_count INTEGER DEFAULT 0,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 10. Plugin Architecture
CREATE TABLE IF NOT EXISTS plugins (
    id SERIAL PRIMARY KEY,
    plugin_code VARCHAR(100) UNIQUE NOT NULL,
    plugin_name VARCHAR(255) NOT NULL,
    description TEXT,
    version VARCHAR(50) DEFAULT '1.0.0',
    author VARCHAR(255),
    entry_point VARCHAR(500),
    settings JSONB,
    is_active BOOLEAN DEFAULT FALSE,
    is_system BOOLEAN DEFAULT FALSE,
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS plugin_hooks (
    id SERIAL PRIMARY KEY,
    plugin_id INTEGER REFERENCES plugins(id) ON DELETE CASCADE,
    hook_name VARCHAR(255) NOT NULL,
    handler_class VARCHAR(500) NOT NULL,
    handler_method VARCHAR(255) NOT NULL,
    priority INTEGER DEFAULT 10,
    is_active BOOLEAN DEFAULT TRUE
);

-- 11. Multi-Language / i18n
CREATE TABLE IF NOT EXISTS languages (
    id SERIAL PRIMARY KEY,
    language_code VARCHAR(10) UNIQUE NOT NULL,
    language_name VARCHAR(100) NOT NULL,
    is_rtl BOOLEAN DEFAULT FALSE,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS translations (
    id SERIAL PRIMARY KEY,
    language_id INTEGER REFERENCES languages(id) ON DELETE CASCADE,
    translation_key VARCHAR(500) NOT NULL,
    translation_value TEXT NOT NULL,
    module VARCHAR(100) DEFAULT 'global',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(language_id, translation_key)
);

-- 12. LDAP / SSO / SAML Authentication
CREATE TABLE IF NOT EXISTS sso_providers (
    id SERIAL PRIMARY KEY,
    provider_name VARCHAR(255) NOT NULL,
    provider_type VARCHAR(50) NOT NULL,
    issuer_url VARCHAR(500),
    client_id VARCHAR(500),
    client_secret TEXT,
    certificate TEXT,
    ldap_host VARCHAR(255),
    ldap_port INTEGER DEFAULT 389,
    ldap_base_dn VARCHAR(500),
    ldap_bind_dn VARCHAR(500),
    ldap_bind_password TEXT,
    ldap_user_filter VARCHAR(500),
    auto_create_users BOOLEAN DEFAULT TRUE,
    default_role_id INTEGER REFERENCES roles(id),
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 13. Dashboard / BI Analytics
CREATE TABLE IF NOT EXISTS dashboard_reports (
    id SERIAL PRIMARY KEY,
    report_name VARCHAR(255) NOT NULL,
    report_type VARCHAR(100) NOT NULL,
    report_config JSONB NOT NULL,
    created_by INTEGER REFERENCES users(id),
    is_shared BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bi_connections (
    id SERIAL PRIMARY KEY,
    connection_name VARCHAR(255) NOT NULL,
    connection_type VARCHAR(100) NOT NULL,
    connection_config JSONB NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 14. Email / Notification Engine
CREATE TABLE IF NOT EXISTS notification_templates (
    id SERIAL PRIMARY KEY,
    template_code VARCHAR(100) UNIQUE NOT NULL,
    template_name VARCHAR(255) NOT NULL,
    subject TEXT NOT NULL,
    body_html TEXT,
    body_text TEXT,
    variables JSONB,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    notification_type VARCHAR(100) NOT NULL,
    title VARCHAR(500) NOT NULL,
    message TEXT,
    link VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notification_subscriptions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    notification_type VARCHAR(100) NOT NULL,
    email_enabled BOOLEAN DEFAULT TRUE,
    in_app_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, notification_type)
);

-- 15. Stability Study Management
CREATE TABLE IF NOT EXISTS stability_studies (
    id SERIAL PRIMARY KEY,
    study_code VARCHAR(100) UNIQUE NOT NULL,
    study_name VARCHAR(500) NOT NULL,
    product_id INTEGER REFERENCES products(id),
    batch_id INTEGER REFERENCES batches(id),
    study_type VARCHAR(100) DEFAULT 'Long Term',
    condition_temperature DECIMAL(5,1),
    condition_humidity DECIMAL(5,1),
    condition_light VARCHAR(100),
    storage_condition TEXT,
    protocol_ref VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Active',
    start_date DATE,
    end_date DATE,
    report_conclusion TEXT,
    created_by INTEGER REFERENCES users(id),
    reviewed_by INTEGER REFERENCES users(id),
    approved_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stability_study_timepoints (
    id SERIAL PRIMARY KEY,
    study_id INTEGER REFERENCES stability_studies(id) ON DELETE CASCADE,
    timepoint_label VARCHAR(100) NOT NULL,
    day_offset INTEGER NOT NULL,
    scheduled_date DATE,
    completed_date DATE,
    status VARCHAR(50) DEFAULT 'Pending',
    notes TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stability_study_results (
    id SERIAL PRIMARY KEY,
    timepoint_id INTEGER REFERENCES stability_study_timepoints(id) ON DELETE CASCADE,
    test_id INTEGER REFERENCES tests(id),
    result_value VARCHAR(255),
    specification_limit VARCHAR(255),
    result_status VARCHAR(50) DEFAULT 'Pending',
    tested_by INTEGER REFERENCES users(id),
    tested_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 16. Environmental Monitoring
CREATE TABLE IF NOT EXISTS env_monitoring_points (
    id SERIAL PRIMARY KEY,
    point_code VARCHAR(100) UNIQUE NOT NULL,
    point_name VARCHAR(255) NOT NULL,
    point_type VARCHAR(100) NOT NULL,
    location_id INTEGER REFERENCES instrument_locations(id),
    area VARCHAR(255),
    min_value DECIMAL(10,2),
    max_value DECIMAL(10,2),
    unit VARCHAR(50),
    alert_threshold_high DECIMAL(10,2),
    alert_threshold_low DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS env_monitoring_readings (
    id SERIAL PRIMARY KEY,
    point_id INTEGER REFERENCES env_monitoring_points(id) ON DELETE CASCADE,
    reading_value DECIMAL(10,2) NOT NULL,
    reading_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recorded_by INTEGER REFERENCES users(id),
    instrument_id INTEGER REFERENCES instruments(id),
    alert_triggered BOOLEAN DEFAULT FALSE,
    alert_severity VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 17. Training Management
CREATE TABLE IF NOT EXISTS training_courses (
    id SERIAL PRIMARY KEY,
    course_code VARCHAR(100) UNIQUE NOT NULL,
    course_name VARCHAR(500) NOT NULL,
    description TEXT,
    course_type VARCHAR(100),
    duration_hours DECIMAL(5,1),
    provider VARCHAR(255),
    requires_certification BOOLEAN DEFAULT FALSE,
    validity_days INTEGER,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS training_assignments (
    id SERIAL PRIMARY KEY,
    course_id INTEGER REFERENCES training_courses(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    assigned_by INTEGER REFERENCES users(id),
    due_date DATE,
    status VARCHAR(50) DEFAULT 'Pending',
    score DECIMAL(5,2),
    completed_date DATE,
    certificate_number VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(course_id, user_id)
);

CREATE TABLE IF NOT EXISTS training_documents (
    id SERIAL PRIMARY KEY,
    course_id INTEGER REFERENCES training_courses(id) ON DELETE CASCADE,
    document_name VARCHAR(500) NOT NULL,
    document_path VARCHAR(1000),
    document_type VARCHAR(100),
    file_size INTEGER,
    uploaded_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 18. Supplier / Vendor Management
CREATE TABLE IF NOT EXISTS suppliers (
    id SERIAL PRIMARY KEY,
    supplier_code VARCHAR(100) UNIQUE NOT NULL,
    supplier_name VARCHAR(500) NOT NULL,
    supplier_type VARCHAR(100),
    address TEXT,
    city VARCHAR(255),
    state VARCHAR(255),
    country VARCHAR(255),
    postal_code VARCHAR(50),
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(100),
    website VARCHAR(500),
    tax_id VARCHAR(100),
    payment_terms VARCHAR(255),
    rating INTEGER DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Active',
    is_approved BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS supplier_qualifications (
    id SERIAL PRIMARY KEY,
    supplier_id INTEGER REFERENCES suppliers(id) ON DELETE CASCADE,
    qualification_type VARCHAR(100) NOT NULL,
    qualification_date DATE DEFAULT CURRENT_DATE,
    expiry_date DATE,
    result VARCHAR(50),
    certificate_number VARCHAR(255),
    audited_by INTEGER REFERENCES users(id),
    notes TEXT,
    attachment_path VARCHAR(1000),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS supplier_products (
    id SERIAL PRIMARY KEY,
    supplier_id INTEGER REFERENCES suppliers(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
    supplier_product_code VARCHAR(255),
    unit_price DECIMAL(12,2),
    lead_time_days INTEGER,
    is_preferred BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(supplier_id, product_id)
);

-- 19. Non-Conformance / Deviation Management
CREATE TABLE IF NOT EXISTS deviations (
    id SERIAL PRIMARY KEY,
    deviation_code VARCHAR(100) UNIQUE NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    deviation_type VARCHAR(100) NOT NULL,
    severity VARCHAR(50) DEFAULT 'Minor',
    source VARCHAR(100),
    source_id INTEGER,
    reported_by INTEGER REFERENCES users(id),
    reported_date DATE DEFAULT CURRENT_DATE,
    status VARCHAR(50) DEFAULT 'Open',
    impact_assessment TEXT,
    root_cause TEXT,
    corrective_action TEXT,
    preventive_action TEXT,
    closure_notes TEXT,
    reviewed_by INTEGER REFERENCES users(id),
    approved_by INTEGER REFERENCES users(id),
    closed_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS deviation_actions (
    id SERIAL PRIMARY KEY,
    deviation_id INTEGER REFERENCES deviations(id) ON DELETE CASCADE,
    action_description TEXT NOT NULL,
    assigned_to INTEGER REFERENCES users(id),
    due_date DATE,
    completed_date DATE,
    status VARCHAR(50) DEFAULT 'Pending',
    priority VARCHAR(50) DEFAULT 'Medium',
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 20. Calibration Management (enhanced - replaces basic one)
CREATE TABLE IF NOT EXISTS calibration_standards (
    id SERIAL PRIMARY KEY,
    standard_code VARCHAR(100) UNIQUE NOT NULL,
    standard_name VARCHAR(255) NOT NULL,
    standard_type VARCHAR(100),
    serial_number VARCHAR(255),
    certificate_number VARCHAR(255),
    calibration_interval_days INTEGER DEFAULT 365,
    last_calibration_date DATE,
    next_calibration_date DATE,
    supplier_id INTEGER REFERENCES suppliers(id),
    status VARCHAR(50) DEFAULT 'Active',
    location VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS calibration_schedules (
    id SERIAL PRIMARY KEY,
    instrument_id INTEGER REFERENCES instruments(id) ON DELETE CASCADE,
    standard_id INTEGER REFERENCES calibration_standards(id),
    schedule_name VARCHAR(255),
    frequency_days INTEGER NOT NULL,
    last_due_date DATE,
    next_due_date DATE,
    assigned_to INTEGER REFERENCES users(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS calibration_records (
    id SERIAL PRIMARY KEY,
    instrument_id INTEGER REFERENCES instruments(id) ON DELETE CASCADE,
    standard_id INTEGER REFERENCES calibration_standards(id),
    calibration_date DATE NOT NULL,
    calibrated_by INTEGER REFERENCES users(id),
    calibration_type VARCHAR(100),
    result VARCHAR(50),
    as_found_value TEXT,
    as_left_value TEXT,
    uncertainty DECIMAL(10,4),
    certificate_number VARCHAR(255),
    certificate_file VARCHAR(1000),
    due_date DATE,
    status VARCHAR(50) DEFAULT 'Completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default language
INSERT INTO languages (language_code, language_name, is_default, is_active) 
VALUES ('en', 'English', TRUE, TRUE);

-- Seed default notification templates
INSERT INTO notification_templates (template_code, template_name, subject, body_html) VALUES
('test_approved', 'Test Result Approved', 'Test Result Approved - [[SAMPLE_CODE]]', '<h2>Test Result Approved</h2><p>Sample: [[SAMPLE_CODE]]<br>Test: [[TEST_NAME]]<br>Result: [[RESULT_VALUE]]<br>Approved by: [[APPROVED_BY]]</p>'),
('oos_created', 'OOS Record Created', 'OOS [[OOS_CODE]] - [[SEVERITY]] - [[PRODUCT_NAME]]', '<h2>Out of Specification Record Created</h2><p>OOS Code: [[OOS_CODE]]<br>Product: [[PRODUCT_NAME]]<br>Severity: [[SEVERITY]]<br>Reported by: [[REPORTED_BY]]</p>'),
('capa_due', 'CAPA Action Due', 'CAPA [[CAPA_CODE]] - Action Due', '<h2>CAPA Action Due</h2><p>CAPA: [[CAPA_CODE]]<br>Action: [[ACTION_DESC]]<br>Due Date: [[DUE_DATE]]</p>'),
('calibration_due', 'Calibration Due', 'Calibration Due: [[INSTRUMENT_NAME]]', '<h2>Calibration Due</h2><p>Instrument: [[INSTRUMENT_NAME]]<br>Due Date: [[DUE_DATE]]</p>'),
('sample_registered', 'New Sample Registered', 'Sample [[SAMPLE_CODE]] Registered', '<h2>New Sample Registered</h2><p>Sample Code: [[SAMPLE_CODE]]<br>Product: [[PRODUCT_NAME]]<br>Customer: [[CUSTOMER_NAME]]</p>'),
('training_assigned', 'Training Assigned', 'Training Assigned: [[COURSE_NAME]]', '<h2>Training Assigned</h2><p>Course: [[COURSE_NAME]]<br>Due Date: [[DUE_DATE]]<br>Assigned by: [[ASSIGNED_BY]]</p>'),
('deviation_opened', 'Deviation Recorded', 'Deviation [[DEVIATION_CODE]] - [[SEVERITY]]', '<h2>Deviation Recorded</h2><p>Code: [[DEVIATION_CODE]]<br>Title: [[TITLE]]<br>Severity: [[SEVERITY]]<br>Reported by: [[REPORTED_BY]]</p>');

-- Seed default notification subscriptions for admin user
INSERT INTO notification_subscriptions (user_id, notification_type)
SELECT id, 'test_approved' FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'Admin');
INSERT INTO notification_subscriptions (user_id, notification_type)
SELECT id, 'oos_created' FROM users WHERE role_id IN (SELECT id FROM roles WHERE name IN ('Admin', 'Reviewer'));
INSERT INTO notification_subscriptions (user_id, notification_type)
SELECT id, 'calibration_due' FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'Admin');
INSERT INTO notification_subscriptions (user_id, notification_type)
SELECT id, 'deviation_opened' FROM users WHERE role_id IN (SELECT id FROM roles WHERE name IN ('Admin', 'Reviewer'));

-- Seed default data retention policies
INSERT INTO data_retention_policies (entity_type, retention_days, action_on_expiry) VALUES
('audit_logs', 2555, 'Archive'),
('sap_sync_logs', 730, 'Archive'),
('login_history', 365, 'Delete'),
('notifications', 365, 'Delete'),
('barcode_scan_logs', 730, 'Archive'),
('env_monitoring_readings', 1095, 'Archive');

-- Seed deployment settings
INSERT INTO deployment_settings (setting_key, setting_value, category) VALUES
('app.mode', 'self-hosted', 'general'),
('app.version', '2.1.0', 'general'),
('maintenance_mode', 'false', 'general'),
('cloud_enabled', 'false', 'cloud'),
('auto_update_enabled', 'false', 'cloud'),
('api_rate_limit', '100', 'api'),
('session_timeout', '120', 'security'),
('max_login_attempts', '5', 'security'),
('data_retention_enabled', 'true', 'compliance'),
('audit_log_retention_days', '2555', 'compliance'),
('notifications_enabled', 'true', 'notifications'),
('email_queue_enabled', 'true', 'notifications'),
('sso_enabled', 'false', 'authentication'),
('multilanguage_enabled', 'true', 'i18n'),
('default_language', 'en', 'i18n');
