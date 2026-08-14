-- ============================================================
-- Scalability enhancements
--   * job queue table (async processing)
--   * database-backed sessions table (multi-instance ready)
--   * performance indexes for hot list / filter queries
-- ============================================================

-- 1. Job queue -------------------------------------------------
CREATE TABLE IF NOT EXISTS jobs (
    id BIGSERIAL PRIMARY KEY,
    queue VARCHAR(50) NOT NULL DEFAULT 'default',
    job VARCHAR(255) NOT NULL,
    payload JSONB NOT NULL DEFAULT '{}',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    available_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reserved_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    last_error TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_jobs_queue_status ON jobs(queue, status, available_at);

-- 2. Database sessions ----------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT NOT NULL DEFAULT '',
    last_activity BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_last_activity ON sessions(last_activity);

-- 3. Performance indexes --------------------------------------
CREATE INDEX IF NOT EXISTS idx_notifications_read_user ON notifications(is_read, user_id);
CREATE INDEX IF NOT EXISTS idx_invoices_created ON invoices(created_at);
CREATE INDEX IF NOT EXISTS idx_coa_documents_created ON coa_documents(created_at);
CREATE INDEX IF NOT EXISTS idx_deviations_created ON deviations(created_at);
CREATE INDEX IF NOT EXISTS idx_deviations_status ON deviations(status);
CREATE INDEX IF NOT EXISTS idx_eln_entries_created ON eln_entries(created_at);
CREATE INDEX IF NOT EXISTS idx_calibration_records_instrument_date ON calibration_records(instrument_id, calibration_date);
CREATE INDEX IF NOT EXISTS idx_environmental_readings_point_created ON environmental_readings(point_id, created_at);
CREATE INDEX IF NOT EXISTS idx_webhook_logs_webhook_created ON webhook_logs(webhook_id, created_at);
CREATE INDEX IF NOT EXISTS idx_suppliers_status ON suppliers(status);
CREATE INDEX IF NOT EXISTS idx_stability_studies_status_created ON stability_studies(status, created_at);
CREATE INDEX IF NOT EXISTS idx_results_sample_revision ON results(sample_test_id, revision);
CREATE INDEX IF NOT EXISTS idx_payments_invoice ON payments(invoice_id);
CREATE INDEX IF NOT EXISTS idx_sample_tests_sample_status ON sample_tests(sample_id, status);
CREATE INDEX IF NOT EXISTS idx_login_history_user_date ON login_history(user_id, login_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_entity_created ON audit_logs(entity_type, entity_id, created_at);
