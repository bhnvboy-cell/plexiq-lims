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
-- SEED: Pharmaceutical Products (APIs & Finished Products)
-- ============================================================
INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'PHARMA-001', 'Amoxicillin Trihydrate IP/USP', 'Semi-synthetic antibiotic active pharmaceutical ingredient for oral suspension and capsules', 'Pharmaceuticals', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'PHARMA-001');

INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'PHARMA-002', 'Paracetamol 500mg Tablets BP/USP', 'Analgesic and antipyretic compressed finished drug product tablets 500mg', 'Pharmaceuticals', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'PHARMA-002');

INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'PHARMA-003', 'Ibuprofen 400mg Film-Coated Tablets', 'Non-steroidal anti-inflammatory drug (NSAID) finished product 400mg caplets', 'Pharmaceuticals', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'PHARMA-003');

INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'PHARMA-004', 'Ceftriaxone Sodium for Injection 1g', 'Third-generation cephalosporin sterile powder for reconstitution and parenteral administration', 'Pharmaceuticals', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'PHARMA-004');

INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'PHARMA-005', 'Metformin HCl 500mg Extended Release Tablets', 'Biguanide oral antidiabetic extended-release finished product tablets 500mg', 'Pharmaceuticals', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'PHARMA-005');

INSERT INTO products (product_code, product_name, description, category, is_active)
SELECT 'PHARMA-006', 'Atorvastatin Calcium Trihydrate USP', 'HMG-CoA reductase inhibitor active pharmaceutical ingredient for cholesterol management', 'Pharmaceuticals', TRUE
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'PHARMA-006');

-- ============================================================
-- SEED: Pharmaceutical Quality Control Tests (12 release parameters)
-- ============================================================
INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text) 
SELECT 'PH-TEST-001', 'Assay of Active Ingredient (HPLC)', m.id, u.id, 98.0, 102.0, '98.0% - 102.0%'
FROM methods m, units u WHERE m.method_code = 'HPLC-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-001');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-002', 'Dissolution Rate (USP Apparatus II)', m.id, u.id, 80.0, 100.0, 'NLT 80.0% in 30 min'
FROM methods m, units u WHERE m.method_code = 'TITR-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-002');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-003', 'Related Substances / Impurities (HPLC)', m.id, u.id, 0.0, 0.5, 'NMT 0.50%'
FROM methods m, units u WHERE m.method_code = 'HPLC-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-003');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-004', 'Water Determination (Karl Fischer)', m.id, u.id, 0.0, 5.0, 'NMT 5.0%'
FROM methods m, units u WHERE m.method_code = 'TITR-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-004');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-005', 'Uniformity of Dosage Units (CU)', m.id, u.id, 0.0, 15.0, 'AV NMT 15.0'
FROM methods m, units u WHERE m.method_code = 'HPLC-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-005');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-006', 'Bacterial Endotoxins (LAL)', m.id, u.id, 0.0, 0.20, 'NMT 0.20 EU/mg'
FROM methods m, units u WHERE m.method_code = 'GRAV-001' AND u.unit_code = 'ppm'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-006');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-007', 'Sterility Testing (Membrane Filtration)', m.id, u.id, 0.0, 0.0, 'Sterile / No Growth'
FROM methods m, units u WHERE m.method_code = 'GRAV-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-007');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-008', 'pH Determination (Potentiometric)', m.id, u.id, 4.0, 7.0, '4.0 - 7.0'
FROM methods m, units u WHERE m.method_code = 'PH-001' AND u.unit_code = 'pH'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-008');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-009', 'Loss on Drying (LOD)', m.id, u.id, 0.0, 1.0, 'NMT 1.0%'
FROM methods m, units u WHERE m.method_code = 'GRAV-001' AND u.unit_code = '%'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-009');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-010', 'Heavy Metals / Elemental Impurities (ICP-MS)', m.id, u.id, 0.0, 10.0, 'NMT 10 ppm'
FROM methods m, units u WHERE m.method_code = 'FTIR-001' AND u.unit_code = 'ppm'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-010');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-011', 'Microbial Enumeration (TAMC)', m.id, u.id, 0.0, 1000.0, 'NMT 1000 CFU/g'
FROM methods m, units u WHERE m.method_code = 'GRAV-001' AND u.unit_code = 'ppm'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-011');

INSERT INTO tests (test_code, test_name, method_id, unit_id, min_spec_limit, max_spec_limit, spec_limit_text)
SELECT 'PH-TEST-012', 'Residual Solvents (GC-Headspace)', m.id, u.id, 0.0, 3000.0, 'NMT 3000 ppm'
FROM methods m, units u WHERE m.method_code = 'TITR-001' AND u.unit_code = 'ppm'
AND NOT EXISTS (SELECT 1 FROM tests WHERE test_code = 'PH-TEST-012');

-- ============================================================
-- Link Pharmaceutical products to Pharmaceutical tests
-- ============================================================
INSERT INTO products_tests (product_id, test_id, sort_order)
SELECT p.id, t.id, row_number() OVER (ORDER BY t.test_code)
FROM products p CROSS JOIN tests t
WHERE p.category = 'Pharmaceuticals' AND t.test_code LIKE 'PH-TEST-%'
AND NOT EXISTS (SELECT 1 FROM products_tests pt WHERE pt.product_id = p.id AND pt.test_id = t.id);
