-- ============================================================
-- Rollback Migration 029: Revert Pharma Products to Legacy Starch & Glucose
-- ============================================================

UPDATE products SET 
    product_code = 'STARCH-001',
    product_name = 'Maize Starch Food Grade',
    description = 'Food grade maize starch for confectionery and bakery applications',
    category = 'Starch',
    product_type = 'Food Material',
    erp_material_number = 'MAT-STARCH-001'
WHERE product_code = 'PHARMA-001';

UPDATE products SET 
    product_code = 'STARCH-002',
    product_name = 'Tapioca Starch Industrial',
    description = 'Industrial grade tapioca starch for adhesives and textiles',
    category = 'Starch',
    product_type = 'Industrial Material',
    erp_material_number = 'MAT-STARCH-002'
WHERE product_code = 'PHARMA-002';

UPDATE products SET 
    product_code = 'STARCH-003',
    product_name = 'Modified Starch E1420',
    description = 'Acetylated starch for food thickening applications',
    category = 'Starch',
    product_type = 'Food Additive',
    erp_material_number = 'MAT-STARCH-003'
WHERE product_code = 'PHARMA-003';

UPDATE products SET 
    product_code = 'GLUCOSE-001',
    product_name = 'Glucose Syrup 42DE',
    description = 'Glucose syrup 42 DE for confectionery and brewing',
    category = 'Glucose',
    product_type = 'Syrup',
    erp_material_number = 'MAT-GLUCOSE-001'
WHERE product_code = 'PHARMA-004';

UPDATE products SET 
    product_code = 'GLUCOSE-002',
    product_name = 'Dextrose Monohydrate',
    description = 'Dextrose monohydrate crystalline powder for food/pharma',
    category = 'Glucose',
    product_type = 'Powder',
    erp_material_number = 'MAT-GLUCOSE-002'
WHERE product_code = 'PHARMA-005';

UPDATE products SET 
    product_code = 'GLUCOSE-003',
    product_name = 'Glucose Syrup 62DE',
    description = 'High conversion glucose syrup 62 DE for confectionery',
    category = 'Glucose',
    product_type = 'Syrup',
    erp_material_number = 'MAT-GLUCOSE-003'
WHERE product_code = 'PHARMA-006';

UPDATE tests SET test_code = 'ST-TEST-001', test_name = 'pH (20% slurry)' WHERE test_code = 'PH-TEST-001';
UPDATE tests SET test_code = 'ST-TEST-002', test_name = 'Moisture Content' WHERE test_code = 'PH-TEST-002';
UPDATE tests SET test_code = 'ST-TEST-003', test_name = 'Viscosity (6% solids, 95°C)' WHERE test_code = 'PH-TEST-003';
UPDATE tests SET test_code = 'ST-TEST-004', test_name = 'Brightness / Whiteness' WHERE test_code = 'PH-TEST-004';
UPDATE tests SET test_code = 'ST-TEST-005', test_name = 'Particle Size (d50)' WHERE test_code = 'PH-TEST-005';
UPDATE tests SET test_code = 'ST-TEST-006', test_name = 'Sulphated Ash' WHERE test_code = 'PH-TEST-006';

UPDATE tests SET test_code = 'GL-TEST-001', test_name = 'Dextrose Equivalent (DE)' WHERE test_code = 'PH-TEST-007';
UPDATE tests SET test_code = 'GL-TEST-002', test_name = 'pH (50% solution)' WHERE test_code = 'PH-TEST-008';
UPDATE tests SET test_code = 'GL-TEST-003', test_name = 'Dry Substance (DS)' WHERE test_code = 'PH-TEST-009';
UPDATE tests SET test_code = 'GL-TEST-004', test_name = 'Colour (ICUMSA)' WHERE test_code = 'PH-TEST-010';
UPDATE tests SET test_code = 'GL-TEST-005', test_name = 'Turbidity (NTU)' WHERE test_code = 'PH-TEST-011';
UPDATE tests SET test_code = 'GL-TEST-006', test_name = 'Conductivity (µS/cm)' WHERE test_code = 'PH-TEST-012';
