-- ============================================================
-- Migration 003: Batch & Product-Test Mapping
-- ============================================================

-- Products-Tests pivot table for product-specific specs
CREATE TABLE IF NOT EXISTS products_tests (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    test_id INTEGER NOT NULL REFERENCES tests(id),
    min_spec_limit NUMERIC(18,6),
    max_spec_limit NUMERIC(18,6),
    spec_limit_text VARCHAR(255),
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(product_id, test_id)
);

-- Batches table (first-class batch entity)
CREATE TABLE IF NOT EXISTS batches (
    id SERIAL PRIMARY KEY,
    batch_number VARCHAR(100) NOT NULL,
    product_id INTEGER REFERENCES products(id),
    batch_size VARCHAR(50),
    manufacture_date DATE,
    expiry_date DATE,
    status VARCHAR(50) DEFAULT 'Registered'
        CHECK (status IN ('Registered','In Progress','Reviewed','Approved','COA Released','Rejected')),
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Link batches to samples
ALTER TABLE samples ADD COLUMN IF NOT EXISTS batch_id INTEGER REFERENCES batches(id);

-- ============================================================
-- SEED: Starch Product with 6 analysis parameters
-- ============================================================
INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'STARCH-001', 'Maize Starch Food Grade', 'Food grade maize starch for confectionery and bakery applications', 'Starch', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'STARCH-001');

INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'STARCH-002', 'Tapioca Starch Industrial', 'Industrial grade tapioca starch for adhesives and textiles', 'Starch', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'STARCH-002');

INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'STARCH-003', 'Modified Starch E1420', 'Acetylated starch for food thickening applications', 'Starch', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'STARCH-003');

-- ============================================================
-- SEED: Glucose Product with 6 analysis parameters
-- ============================================================
INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'GLUCOSE-001', 'Glucose Syrup 42DE', 'Glucose syrup 42 DE for confectionery and brewing', 'Glucose', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'GLUCOSE-001');

INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'GLUCOSE-002', 'Dextrose Monohydrate', 'Dextrose monohydrate crystalline powder for food/pharma', 'Glucose', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'GLUCOSE-002');

INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'GLUCOSE-003', 'Glucose Syrup 62DE', 'High conversion glucose syrup 62 DE for confectionery', 'Glucose', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'GLUCOSE-003');

-- ============================================================
-- SEED: Starch Analysis Tests (6 parameters)
-- ============================================================
INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text) 
SELECT 'ST-TEST-001', 'pH (20% slurry)', m.id, u.id, 5.0, 7.0, '5.0 - 7.0'
FROM methods m, units u WHERE m.method_code = 'PH-001' AND u.unit_code = 'pH'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'ST-TEST-001');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'ST-TEST-002', 'Moisture Content', m.id, u.id, 0, 14.0, 'NMT 14.0%'
FROM methods m, units u WHERE m.method_code = 'GRAV-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'ST-TEST-002');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'ST-TEST-003', 'Viscosity (6% solids, 95°C)', m.id, u.id, 200, 800, '200 - 800 cP'
FROM methods m, units u WHERE m.method_code = 'HPLC-001' AND u.unit_code = 'cP'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'ST-TEST-003');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'ST-TEST-004', 'Brightness / Whiteness', m.id, u.id, 85, 100, '85.0% - 100.0%'
FROM methods m, units u WHERE m.method_code = 'FTIR-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'ST-TEST-004');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'ST-TEST-005', 'Particle Size (d50)', m.id, u.id, 10, 35, '10 - 35 µm'
FROM methods m, units u WHERE m.method_code = 'HPLC-001' AND u.unit_code = 'mm'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'ST-TEST-005');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'ST-TEST-006', 'Sulphated Ash', m.id, u.id, 0, 0.5, 'NMT 0.5%'
FROM methods m, units u WHERE m.method_code = 'GRAV-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'ST-TEST-006');

-- ============================================================
-- SEED: Glucose Analysis Tests (6 parameters)
-- ============================================================
INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'GL-TEST-001', 'Dextrose Equivalent (DE)', m.id, u.id, 40, 44, '40 - 44'
FROM methods m, units u WHERE m.method_code = 'TITR-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'GL-TEST-001');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'GL-TEST-002', 'pH (50% solution)', m.id, u.id, 4.0, 6.0, '4.0 - 6.0'
FROM methods m, units u WHERE m.method_code = 'PH-001' AND u.unit_code = 'pH'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'GL-TEST-002');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'GL-TEST-003', 'Dry Substance (DS)', m.id, u.id, 70, 82, '70% - 82%'
FROM methods m, units u WHERE m.method_code = 'GRAV-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'GL-TEST-003');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'GL-TEST-004', 'Colour (ICUMSA)', m.id, u.id, 0, 200, 'NMT 200 IU'
FROM methods m, units u WHERE m.method_code = 'FTIR-001' AND u.unit_code = 'cP'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'GL-TEST-004');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'GL-TEST-005', 'Turbidity (NTU)', m.id, u.id, 0, 50, 'NMT 50 NTU'
FROM methods m, units u WHERE m.method_code = 'HPLC-001' AND u.unit_code = 'mm'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'GL-TEST-005');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'GL-TEST-006', 'Conductivity (µS/cm)', m.id, u.id, 0, 50, 'NMT 50 µS/cm'
FROM methods m, units u WHERE m.method_code = 'TITR-001' AND u.unit_code = 'ppm'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'GL-TEST-006');

-- ============================================================
-- Link Starch products to Starch tests
-- ============================================================
INSERT INTO products_tests (product_id, test_id, sort_order)
SELECT p.id, t.id, row_number() OVER (ORDER BY t.test_code)
FROM products p CROSS JOIN tests t
WHERE p.category = 'Starch' AND t.test_code LIKE 'ST-TEST-%'
AND NOT EXISTS (SELECT 1 FROM products_tests pt WHERE pt.product_id = p.id AND pt.test_id = t.id);

-- ============================================================
-- Link Glucose products to Glucose tests
-- ============================================================
INSERT INTO products_tests (product_id, test_id, sort_order)
SELECT p.id, t.id, row_number() OVER (ORDER BY t.test_code)
FROM products p CROSS JOIN tests t
WHERE p.category = 'Glucose' AND t.test_code LIKE 'GL-TEST-%'
AND NOT EXISTS (SELECT 1 FROM products_tests pt WHERE pt.product_id = p.id AND pt.test_id = t.id);
