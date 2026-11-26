-- Rollback Migration 028
DROP TABLE IF EXISTS result_lineage CASCADE;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS retest_of_test_id;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS is_retest;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS retest_count;
ALTER TABLE results DROP COLUMN IF EXISTS retest_parent_result_id;
ALTER TABLE results DROP COLUMN IF EXISTS invalidated_at;
ALTER TABLE results DROP COLUMN IF EXISTS invalidated_by;
ALTER TABLE results DROP COLUMN IF EXISTS invalidation_reason;
ALTER TABLE results DROP COLUMN IF EXISTS is_invalidated;
