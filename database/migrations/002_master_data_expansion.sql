-- ============================================================
-- PlexiQ LIMS - Master Data Expansion
-- Adds: sample_types, instrument_locations, instrument_calibrations,
--       chemical_inventory, email_configurations
-- ============================================================

BEGIN;

-- ============================================================
-- 1. SAMPLE TYPES
-- ============================================================
CREATE TABLE IF NOT EXISTS sample_types (
    id SERIAL PRIMARY KEY,
    type_code VARCHAR(50) UNIQUE NOT NULL,
    type_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO sample_types (type_code, type_name, description) VALUES
    ('ROUTINE', 'Routine', 'Routine production sample'),
    ('RAW-MAT', 'Raw Material', 'Incoming raw material sample'),
    ('IN-PROCESS', 'In-Process', 'In-process intermediate sample'),
    ('FINISHED', 'Finished Product', 'Finished product sample'),
    ('STABILITY', 'Stability', 'Stability study sample'),
    ('ENV', 'Environmental', 'Environmental monitoring sample'),
    ('REFERENCE', 'Reference Standard', 'Reference standard sample')
ON CONFLICT (type_code) DO NOTHING;

-- ============================================================
-- 2. INSTRUMENT LOCATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS instrument_locations (
    id SERIAL PRIMARY KEY,
    location_code VARCHAR(50) UNIQUE NOT NULL,
    location_name VARCHAR(255) NOT NULL,
    building VARCHAR(255),
    floor VARCHAR(50),
    room VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO instrument_locations (location_code, location_name, building, floor, room) VALUES
    ('LAB-A-101', 'QC Lab A - Room 101', 'Main Building', '1st Floor', '101'),
    ('LAB-A-102', 'QC Lab A - Room 102', 'Main Building', '1st Floor', '102'),
    ('LAB-B-201', 'QC Lab B - Room 201', 'Main Building', '2nd Floor', '201'),
    ('LAB-B-202', 'QC Lab B - Room 202', 'Main Building', '2nd Floor', '202'),
    ('MICRO-001', 'Microbiology Lab', 'Annex Building', 'Ground Floor', 'G01'),
    ('WET-CHEM', 'Wet Chemistry Lab', 'Main Building', 'Ground Floor', 'G05')
ON CONFLICT (location_code) DO NOTHING;

-- ============================================================
-- 3. EXTEND INSTRUMENTS TABLE
-- ============================================================
ALTER TABLE instruments ADD COLUMN IF NOT EXISTS location_id INTEGER REFERENCES instrument_locations(id);
ALTER TABLE instruments ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100);
ALTER TABLE instruments ADD COLUMN IF NOT EXISTS purchase_date DATE;
ALTER TABLE instruments ADD COLUMN IF NOT EXISTS warranty_expiry DATE;
ALTER TABLE instruments ADD COLUMN IF NOT EXISTS last_calibration_date DATE;
ALTER TABLE instruments ADD COLUMN IF NOT EXISTS next_calibration_date DATE;
ALTER TABLE instruments ADD COLUMN IF NOT EXISTS instrument_status VARCHAR(30) DEFAULT 'Active' CHECK (instrument_status IN ('Active','In Maintenance','Out of Service','Retired'));

-- ============================================================
-- 4. INSTRUMENT CALIBRATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS instrument_calibrations (
    id SERIAL PRIMARY KEY,
    instrument_id INTEGER REFERENCES instruments(id) ON DELETE CASCADE,
    calibration_date DATE NOT NULL,
    calibrated_by VARCHAR(255),
    calibration_standard VARCHAR(255),
    result VARCHAR(50) CHECK (result IN ('Pass','Fail','Conditional')),
    certificate_number VARCHAR(100),
    next_calibration_date DATE,
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_calibrations_instrument ON instrument_calibrations(instrument_id);

-- ============================================================
-- 5. CHEMICAL INVENTORY
-- ============================================================
CREATE TABLE IF NOT EXISTS chemical_inventory (
    id SERIAL PRIMARY KEY,
    chemical_name VARCHAR(255) NOT NULL,
    cas_number VARCHAR(50),
    catalog_number VARCHAR(100),
    supplier VARCHAR(255),
    unit_type VARCHAR(50) NOT NULL,
    quantity DECIMAL(12,4) DEFAULT 0,
    minimum_quantity DECIMAL(12,4) DEFAULT 0,
    unit_price DECIMAL(12,4),
    storage_location VARCHAR(255),
    hazard_symbols TEXT,
    safety_data_sheet TEXT,
    expiry_date DATE,
    received_date DATE,
    opened_date DATE,
    status VARCHAR(20) DEFAULT 'In Stock' CHECK (status IN ('In Stock','Low Stock','Expired','Depleted')),
    created_by INTEGER REFERENCES users(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_chemical_status ON chemical_inventory(status);

INSERT INTO chemical_inventory (chemical_name, cas_number, catalog_number, supplier, unit_type, quantity, minimum_quantity, storage_location) VALUES
    ('Sodium Hydroxide (1N)', '1310-73-2', 'NaOH-1N-001', 'Sigma-Aldrich', 'L', 2.5, 0.5, 'Cabinet A-1'),
    ('Hydrochloric Acid (1N)', '7647-01-0', 'HCl-1N-001', 'Merck', 'L', 1.5, 0.5, 'Cabinet A-2'),
    ('Methanol (HPLC Grade)', '67-56-1', 'MeOH-HPLC-001', 'Thermo Fisher', 'L', 4.0, 1.0, 'Solvent Cabinet'),
    ('Acetonitrile (HPLC Grade)', '75-05-8', 'ACN-HPLC-001', 'Thermo Fisher', 'L', 3.0, 1.0, 'Solvent Cabinet'),
    ('Water (HPLC Grade)', '7732-18-5', 'H2O-HPLC-001', 'In-House', 'L', 10.0, 2.0, 'Water Purification System')
ON CONFLICT DO NOTHING;

-- ============================================================
-- 6. EMAIL CONFIGURATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS email_configurations (
    id SERIAL PRIMARY KEY,
    config_name VARCHAR(100) NOT NULL,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INTEGER DEFAULT 587,
    smtp_encryption VARCHAR(20) DEFAULT 'tls' CHECK (smtp_encryption IN ('none','ssl','tls')),
    smtp_username VARCHAR(255),
    smtp_password TEXT,
    from_address VARCHAR(255) NOT NULL,
    from_name VARCHAR(255),
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO email_configurations (config_name, smtp_host, smtp_port, smtp_encryption, from_address, from_name, is_default) VALUES
    ('Default SMTP', 'smtp.gmail.com', 587, 'tls', 'lims@yourlab.com', 'PlexiQ LIMS', TRUE)
ON CONFLICT DO NOTHING;

-- ============================================================
-- 7. ADD SAMPLE TYPE TO SAMPLES
-- ============================================================
ALTER TABLE samples ADD COLUMN IF NOT EXISTS sample_type_id INTEGER REFERENCES sample_types(id);
ALTER TABLE samples ADD COLUMN IF NOT EXISTS sample_nature VARCHAR(100);
ALTER TABLE samples ADD COLUMN IF NOT EXISTS sampling_date DATE;
ALTER TABLE samples ADD COLUMN IF NOT EXISTS sampled_by VARCHAR(255);
ALTER TABLE samples ADD COLUMN IF NOT EXISTS sampling_point VARCHAR(255);

COMMIT;
