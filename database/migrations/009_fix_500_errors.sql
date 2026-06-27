-- Fix 500 errors: add missing columns and tables for DashboardCustomController

-- Add widget_order column to dashboard_widgets
ALTER TABLE dashboard_widgets ADD COLUMN IF NOT EXISTS widget_order INTEGER DEFAULT 0;

-- Add proper columns for dashboard_customize integration  
ALTER TABLE dashboard_widgets ADD COLUMN IF NOT EXISTS widget_name VARCHAR(255);
ALTER TABLE dashboard_widgets ADD COLUMN IF NOT EXISTS widget_config JSONB;
ALTER TABLE dashboard_widgets ADD COLUMN IF NOT EXISTS column_index INTEGER DEFAULT 0;

-- Available widgets lookup table
CREATE TABLE IF NOT EXISTS available_widgets (
    id SERIAL PRIMARY KEY,
    widget_key VARCHAR(100) UNIQUE NOT NULL,
    widget_name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT 'General',
    default_config JSONB DEFAULT '{}',
    icon VARCHAR(100) DEFAULT 'bi-grid',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dashboard filters (for saved filter functionality)
CREATE TABLE IF NOT EXISTS dashboard_filters (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    filter_name VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100) DEFAULT 'Sample',
    filter_data TEXT,
    criteria TEXT,
    is_global BOOLEAN DEFAULT FALSE,
    is_shared BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed available widgets
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
