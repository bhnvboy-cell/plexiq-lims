-- ====================================================================
-- Rollback: rollback_019_core_lims_depth.sql
-- Description: Rollback migration 019 Core LIMS Depth
-- ====================================================================

-- 7. TAT & SLA
ALTER TABLE sample_tests DROP COLUMN IF EXISTS tat_breached;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS completed_at;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS target_completion_at;
ALTER TABLE tests DROP COLUMN IF EXISTS sla_hours;

-- 6. COA LAYOUT SCHEMA
ALTER TABLE coa_templates DROP COLUMN IF EXISTS layout_schema;

-- 5. LABEL TEMPLATES
DROP TABLE IF EXISTS label_templates CASCADE;

-- 4. OOT
DROP TABLE IF EXISTS oot_incidents CASCADE;
DROP TABLE IF EXISTS oot_rules CASCADE;

-- 3. CALCULATION FORMULAS
DROP TABLE IF EXISTS calculation_formulas CASCADE;

-- 2. STORAGE
DROP TABLE IF EXISTS storage_temperature_logs CASCADE;
DROP TABLE IF EXISTS sample_storage_assignments CASCADE;
DROP TABLE IF EXISTS storage_locations CASCADE;

-- 1. SAMPLE HIERARCHY
DROP INDEX IF EXISTS idx_samples_genealogy_path;
DROP INDEX IF EXISTS idx_samples_parent_id;
ALTER TABLE samples DROP COLUMN IF EXISTS composite_components;
ALTER TABLE samples DROP COLUMN IF EXISTS aliquot_index;
ALTER TABLE samples DROP COLUMN IF EXISTS genealogy_path;
ALTER TABLE samples DROP COLUMN IF EXISTS hierarchy_type;
ALTER TABLE samples DROP COLUMN IF EXISTS parent_sample_id;
