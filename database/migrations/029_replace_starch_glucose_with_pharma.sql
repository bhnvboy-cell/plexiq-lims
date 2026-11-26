-- ============================================================
-- Migration 029: Replace Starch & Glucose Demo Data with Pharma Products
-- Standard: 21 CFR Part 211 / GAMP 5 Life Sciences Alignment
-- ============================================================

-- 1. Update legacy Starch & Glucose products to active Pharmaceutical products
UPDATE products SET 
    product_code = 'PHARMA-001',
    product_name = 'Amoxicillin Trihydrate IP/USP',
    description = 'Semi-synthetic antibiotic active pharmaceutical ingredient for oral suspension and capsules',
    category = 'Pharmaceuticals',
    product_type = 'API',
    erp_material_number = 'MAT-PHARM-001',
    updated_at = CURRENT_TIMESTAMP
WHERE product_code = 'STARCH-001';

UPDATE products SET 
    product_code = 'PHARMA-002',
    product_name = 'Paracetamol 500mg Tablets BP/USP',
    description = 'Analgesic and antipyretic compressed finished drug product tablets 500mg',
    category = 'Pharmaceuticals',
    product_type = 'Finished Product',
    erp_material_number = 'MAT-PHARM-002',
    updated_at = CURRENT_TIMESTAMP
WHERE product_code = 'STARCH-002';

UPDATE products SET 
    product_code = 'PHARMA-003',
    product_name = 'Ibuprofen 400mg Film-Coated Tablets',
    description = 'Non-steroidal anti-inflammatory drug (NSAID) finished product 400mg caplets',
    category = 'Pharmaceuticals',
    product_type = 'Finished Product',
    erp_material_number = 'MAT-PHARM-003',
    updated_at = CURRENT_TIMESTAMP
WHERE product_code = 'STARCH-003';

UPDATE products SET 
    product_code = 'PHARMA-004',
    product_name = 'Ceftriaxone Sodium for Injection 1g',
    description = 'Third-generation cephalosporin sterile powder for reconstitution and parenteral administration',
    category = 'Pharmaceuticals',
    product_type = 'Sterile Injectable',
    erp_material_number = 'MAT-PHARM-004',
    updated_at = CURRENT_TIMESTAMP
WHERE product_code = 'GLUCOSE-001';

UPDATE products SET 
    product_code = 'PHARMA-005',
    product_name = 'Metformin HCl 500mg Extended Release Tablets',
    description = 'Biguanide oral antidiabetic extended-release finished product tablets 500mg',
    category = 'Pharmaceuticals',
    product_type = 'Finished Product',
    erp_material_number = 'MAT-PHARM-005',
    updated_at = CURRENT_TIMESTAMP
WHERE product_code = 'GLUCOSE-002';

UPDATE products SET 
    product_code = 'PHARMA-006',
    product_name = 'Atorvastatin Calcium Trihydrate USP',
    description = 'HMG-CoA reductase inhibitor active pharmaceutical ingredient for cholesterol management',
    category = 'Pharmaceuticals',
    product_type = 'API',
    erp_material_number = 'MAT-PHARM-006',
    updated_at = CURRENT_TIMESTAMP
WHERE product_code = 'GLUCOSE-003';

-- 2. Update analytical tests from food/starch to pharma release testing parameters
UPDATE tests SET 
    test_code = 'PH-TEST-001',
    test_name = 'Assay of Active Ingredient (HPLC)',
    min_spec_limit = 98.000000,
    max_spec_limit = 102.000000,
    spec_limit_text = '98.0% - 102.0%'
WHERE test_code = 'ST-TEST-001';

UPDATE tests SET 
    test_code = 'PH-TEST-002',
    test_name = 'Dissolution Rate (USP Apparatus II)',
    min_spec_limit = 80.000000,
    max_spec_limit = 100.000000,
    spec_limit_text = 'NLT 80.0% in 30 min'
WHERE test_code = 'ST-TEST-002';

UPDATE tests SET 
    test_code = 'PH-TEST-003',
    test_name = 'Related Substances / Impurities (HPLC)',
    min_spec_limit = 0.000000,
    max_spec_limit = 0.500000,
    spec_limit_text = 'NMT 0.50%'
WHERE test_code = 'ST-TEST-003';

UPDATE tests SET 
    test_code = 'PH-TEST-004',
    test_name = 'Water Determination (Karl Fischer)',
    min_spec_limit = 0.000000,
    max_spec_limit = 5.000000,
    spec_limit_text = 'NMT 5.0%'
WHERE test_code = 'ST-TEST-004';

UPDATE tests SET 
    test_code = 'PH-TEST-005',
    test_name = 'Uniformity of Dosage Units (CU)',
    min_spec_limit = 0.000000,
    max_spec_limit = 15.000000,
    spec_limit_text = 'AV NMT 15.0'
WHERE test_code = 'ST-TEST-005';

UPDATE tests SET 
    test_code = 'PH-TEST-006',
    test_name = 'Bacterial Endotoxins (LAL)',
    min_spec_limit = 0.000000,
    max_spec_limit = 0.200000,
    spec_limit_text = 'NMT 0.20 EU/mg'
WHERE test_code = 'ST-TEST-006';

UPDATE tests SET 
    test_code = 'PH-TEST-007',
    test_name = 'Sterility Testing (Membrane Filtration)',
    min_spec_limit = 0.000000,
    max_spec_limit = 0.000000,
    spec_limit_text = 'Sterile / No Growth'
WHERE test_code = 'GL-TEST-001';

UPDATE tests SET 
    test_code = 'PH-TEST-008',
    test_name = 'pH Determination (Potentiometric)',
    min_spec_limit = 4.000000,
    max_spec_limit = 7.000000,
    spec_limit_text = '4.0 - 7.0'
WHERE test_code = 'GL-TEST-002';

UPDATE tests SET 
    test_code = 'PH-TEST-009',
    test_name = 'Loss on Drying (LOD)',
    min_spec_limit = 0.000000,
    max_spec_limit = 1.000000,
    spec_limit_text = 'NMT 1.0%'
WHERE test_code = 'GL-TEST-003';

UPDATE tests SET 
    test_code = 'PH-TEST-010',
    test_name = 'Heavy Metals / Elemental Impurities (ICP-MS)',
    min_spec_limit = 0.000000,
    max_spec_limit = 10.000000,
    spec_limit_text = 'NMT 10 ppm'
WHERE test_code = 'GL-TEST-004';

UPDATE tests SET 
    test_code = 'PH-TEST-011',
    test_name = 'Microbial Enumeration (TAMC)',
    min_spec_limit = 0.000000,
    max_spec_limit = 1000.000000,
    spec_limit_text = 'NMT 1000 CFU/g'
WHERE test_code = 'GL-TEST-005';

UPDATE tests SET 
    test_code = 'PH-TEST-012',
    test_name = 'Residual Solvents (GC-Headspace)',
    min_spec_limit = 0.000000,
    max_spec_limit = 3000.000000,
    spec_limit_text = 'NMT 3000 ppm'
WHERE test_code = 'GL-TEST-006';

-- 3. Update SPC parameters from Starch/Glucose to Pharma Critical Quality Attributes (CQAs)
UPDATE spc_parameters SET
    parameter_code = 'ASSAY-001',
    parameter_name = 'Assay Level by HPLC',
    category = 'Potency',
    unit = '% w/w',
    spec_min = 98.0,
    spec_max = 102.0,
    spec_target = 100.0,
    ucl = 101.5,
    lcl = 98.5
WHERE parameter_code = 'DS-001';

UPDATE spc_parameters SET
    parameter_code = 'DISS-001',
    parameter_name = 'Dissolution (30 min)',
    category = 'Dissolution',
    unit = '%',
    spec_min = 80.0,
    spec_max = 100.0,
    spec_target = 92.0,
    ucl = 98.0,
    lcl = 85.0
WHERE parameter_code = 'DP1-001';

-- 4. Clean up any text notes in batches referencing Starch or Glucose
UPDATE batches SET 
    notes = 'Pharmaceutical production batch for commercial release'
WHERE notes LIKE '%Starch%' OR notes LIKE '%Glucose%' OR notes = 'Beta batch for testing';
