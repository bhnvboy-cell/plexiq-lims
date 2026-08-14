-- Notification Settings (required by Notifications module)
-- Table created during the module fix pass; add to fresh installs.

CREATE TABLE IF NOT EXISTS notification_settings (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email_notifications BOOLEAN NOT NULL DEFAULT TRUE,
    browser_notifications BOOLEAN NOT NULL DEFAULT TRUE,
    digest_frequency VARCHAR(50) NOT NULL DEFAULT 'daily',
    notify_on_sample_status BOOLEAN NOT NULL DEFAULT TRUE,
    notify_on_result_entry BOOLEAN NOT NULL DEFAULT TRUE,
    notify_on_certificate BOOLEAN NOT NULL DEFAULT TRUE,
    notify_on_deviation BOOLEAN NOT NULL DEFAULT TRUE,
    quiet_hours_start TIME,
    quiet_hours_end TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT notification_settings_user_id_unique UNIQUE (user_id)
);
