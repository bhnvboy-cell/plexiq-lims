-- Migration 004: User Workspace Shortcuts
CREATE TABLE IF NOT EXISTS user_shortcuts (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    icon VARCHAR(50) DEFAULT 'bi-link',
    color VARCHAR(20) DEFAULT '#0d6efd',
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default shortcuts for admin user (id=1)
INSERT INTO user_shortcuts (user_id, title, url, icon, color, sort_order) VALUES
(1, 'Create Batch', '/batches/create', 'bi-box-seam', '#0d6efd', 1),
(1, 'Result Entry', '/tests/pending', 'bi-clipboard-data', '#198754', 2),
(1, 'Pending Review', '/tests/review', 'bi-check-circle', '#ffc107', 3),
(1, 'Master Data', '/master', 'bi-sliders', '#6f42c1', 4),
(1, 'SPC Charts', '/spc', 'bi-bar-chart-steps', '#dc3545', 5),
(1, 'Samples', '/samples', 'bi-collection', '#0dcaf0', 6),
(1, 'COA', '/coa', 'bi-file-text', '#20c997', 7),
(1, 'OOS Events', '/oos', 'bi-exclamation-triangle', '#fd7e14', 8);
