-- ==============================================================================
-- Rollback 025: Commercial Master Data Spine
-- ==============================================================================

DROP TRIGGER IF EXISTS trg_spec_version_immutability ON specification_versions;
DROP FUNCTION IF EXISTS enforce_spec_version_immutability();

DROP TABLE IF EXISTS product_test_plans CASCADE;
DROP TABLE IF EXISTS specification_limits CASCADE;
DROP TABLE IF EXISTS components CASCADE;
DROP TABLE IF EXISTS analyses CASCADE;
DROP TABLE IF EXISTS specification_versions CASCADE;
DROP TABLE IF EXISTS specifications CASCADE;

ALTER TABLE products DROP COLUMN IF EXISTS product_type;
ALTER TABLE products DROP COLUMN IF EXISTS erp_material_number;
ALTER TABLE products DROP COLUMN IF EXISTS site_id;
ALTER TABLE products DROP COLUMN IF EXISTS status;
