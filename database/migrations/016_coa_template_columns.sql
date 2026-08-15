-- PlexiQ LIMS - COA Template & Document Schema Fixes
-- Adds columns that controllers/services read but the schema never defined.
-- Idempotent: safe to re-run.

ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS page_size VARCHAR(20) DEFAULT 'A4';
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS orientation VARCHAR(20) DEFAULT 'portrait';
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS margin_top INTEGER DEFAULT 15;
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS margin_bottom INTEGER DEFAULT 15;
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS margin_left INTEGER DEFAULT 15;
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS margin_right INTEGER DEFAULT 15;
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS logo_path VARCHAR(500);
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS scada_logo_path VARCHAR(500);
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS watermark_text VARCHAR(500);
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS show_qr_code BOOLEAN DEFAULT TRUE;
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS show_barcode BOOLEAN DEFAULT TRUE;
ALTER TABLE coa_templates ADD COLUMN IF NOT EXISTS show_signature BOOLEAN DEFAULT TRUE;

ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS approved_by INTEGER REFERENCES users(id);
ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP;
ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS reviewed_by INTEGER REFERENCES users(id);
ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP;
ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS amendment_no INTEGER DEFAULT 0;
ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS supersedes_document_id INTEGER REFERENCES coa_documents(id);
ALTER TABLE coa_documents ADD COLUMN IF NOT EXISTS revision_reason TEXT;

-- Backfill defaults on existing templates so PDF generation has values.
UPDATE coa_templates SET
    page_size = COALESCE(page_size, 'A4'),
    orientation = COALESCE(orientation, 'portrait'),
    margin_top = COALESCE(margin_top, 15),
    margin_bottom = COALESCE(margin_bottom, 15),
    margin_left = COALESCE(margin_left, 15),
    margin_right = COALESCE(margin_right, 15),
    show_qr_code = COALESCE(show_qr_code, TRUE),
    show_barcode = COALESCE(show_barcode, TRUE),
    show_signature = COALESCE(show_signature, TRUE)
WHERE is_active = TRUE;
