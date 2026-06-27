-- Comprehensive fix for all remaining 500 errors

-- ==========================================
-- 1. Environmental Monitoring (controller expects environmental_* names)
-- ==========================================
DROP TABLE IF EXISTS env_monitoring_readings CASCADE;
DROP TABLE IF EXISTS env_monitoring_points CASCADE;

CREATE TABLE IF NOT EXISTS environmental_points (
    id SERIAL PRIMARY KEY,
    point_name VARCHAR(255) NOT NULL,
    location_name VARCHAR(255),
    monitoring_type VARCHAR(100) DEFAULT 'Temperature',
    unit VARCHAR(50) DEFAULT '°C',
    min_threshold DECIMAL(10,2),
    max_threshold DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS environmental_readings (
    id SERIAL PRIMARY KEY,
    point_id INTEGER REFERENCES environmental_points(id) ON DELETE CASCADE,
    reading_value DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) DEFAULT '°C',
    recorded_by INTEGER REFERENCES users(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS environmental_alerts (
    id SERIAL PRIMARY KEY,
    point_id INTEGER REFERENCES environmental_points(id) ON DELETE CASCADE,
    alert_type VARCHAR(100) NOT NULL,
    reading_value DECIMAL(10,2),
    threshold_value DECIMAL(10,2),
    message TEXT,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_by INTEGER REFERENCES users(id),
    resolved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- 2. SSO: add is_default column
-- ==========================================
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS is_default BOOLEAN DEFAULT FALSE;
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS client_id VARCHAR(500);
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS client_secret TEXT;
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS redirect_url VARCHAR(500);
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS authorize_url VARCHAR(500);
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS token_url VARCHAR(500);
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS userinfo_url VARCHAR(500);
ALTER TABLE sso_providers ADD COLUMN IF NOT EXISTS scope VARCHAR(500) DEFAULT 'openid email profile';

-- ==========================================
-- 3. Calibration records: add performed_by
-- ==========================================
ALTER TABLE calibration_records ADD COLUMN IF NOT EXISTS performed_by INTEGER REFERENCES users(id);
ALTER TABLE calibration_records ADD COLUMN IF NOT EXISTS calibration_date DATE;
ALTER TABLE calibration_records ADD COLUMN IF NOT EXISTS calibrated_by VARCHAR(255);
ALTER TABLE calibration_records ADD COLUMN IF NOT EXISTS certificate_number VARCHAR(255);
ALTER TABLE calibration_records ADD COLUMN IF NOT EXISTS next_calibration_date DATE;
ALTER TABLE calibration_records ADD COLUMN IF NOT EXISTS notes TEXT;

-- ==========================================
-- 4. Compliance: consent_logs table
-- ==========================================
CREATE TABLE IF NOT EXISTS consent_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    consent_type VARCHAR(100) NOT NULL,
    consent_granted BOOLEAN DEFAULT TRUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- 5. DEVIATIONS: add product_id column
-- ==========================================
ALTER TABLE deviations ADD COLUMN IF NOT EXISTS product_id INTEGER REFERENCES products(id);
ALTER TABLE deviations ADD COLUMN IF NOT EXISTS deviation_type VARCHAR(100);
ALTER TABLE deviations ADD COLUMN IF NOT EXISTS sample_id INTEGER REFERENCES samples(id);
ALTER TABLE deviations ADD COLUMN IF NOT EXISTS created_by INTEGER REFERENCES users(id);

-- ==========================================
-- 6. BI Connections: add name column alias (view uses ORDER BY name)
-- ==========================================
ALTER TABLE bi_connections ADD COLUMN IF NOT EXISTS name VARCHAR(255);
UPDATE bi_connections SET name = connection_name WHERE name IS NULL;

-- ==========================================
-- 5b. Compliance: privacy_logs table
-- ==========================================
CREATE TABLE IF NOT EXISTS privacy_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    action_type VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- 5c. Compliance: data_export_requests table
-- ==========================================
CREATE TABLE IF NOT EXISTS data_export_requests (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    requested_by INTEGER REFERENCES users(id),
    status VARCHAR(50) DEFAULT 'Pending',
    notes TEXT,
    exported_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- 7. Pre-seed: available_widgets (safety check)
-- ==========================================
INSERT INTO available_widgets (widget_key, widget_name, description, category, icon) VALUES
('stats_samples', 'Sample Statistics', 'Overview of sample counts by status', 'Statistics', 'bi-box-seam'),
('stats_batches', 'Batch Statistics', 'Batch processing status overview', 'Statistics', 'bi-collection'),
('chart_tests', 'Test Results Chart', 'Test results distribution chart', 'Charts', 'bi-bar-chart'),
('chart_trends', 'Trend Analysis', 'Quality trends over time', 'Charts', 'bi-graph-up'),
('table_recent_samples', 'Recent Samples', 'Recently created samples', 'Tables', 'bi-table'),
('table_recent_results', 'Recent Results', 'Most recent test results', 'Tables', 'bi-file-text'),
('list_activity', 'Activity Feed', 'Recent system activity', 'Lists', 'bi-list-ul'),
('list_notifications', 'Notifications', 'Unread notifications', 'Lists', 'bi-bell'),
('alert_oos', 'OOS Alerts', 'Out of specification alerts', 'Alerts', 'bi-exclamation-triangle'),
('alert_calibration', 'Calibration Due', 'Upcoming calibration due dates', 'Alerts', 'bi-tools'),
('kpi_throughput', 'Throughput KPI', 'Sample throughput metrics', 'KPIs', 'bi-speedometer2'),
('kpi_quality', 'Quality Score', 'Overall quality score', 'KPIs', 'bi-award')
ON CONFLICT (widget_key) DO NOTHING;
