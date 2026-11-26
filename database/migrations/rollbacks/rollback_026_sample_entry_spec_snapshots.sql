-- ==============================================================================
-- Rollback 026: Sample Entry Follows Product & Frozen Specification Snapshots
-- ==============================================================================

DROP TABLE IF EXISTS sample_lifecycle_transitions CASCADE;
DROP TABLE IF EXISTS sample_templates CASCADE;

ALTER TABLE results DROP COLUMN IF EXISTS spec_version_id;
ALTER TABLE results DROP COLUMN IF EXISTS component_id;
ALTER TABLE results DROP COLUMN IF EXISTS spec_min_snapshot;
ALTER TABLE results DROP COLUMN IF EXISTS spec_max_snapshot;
ALTER TABLE results DROP COLUMN IF EXISTS spec_target_snapshot;
ALTER TABLE results DROP COLUMN IF EXISTS spec_text_snapshot;
ALTER TABLE results DROP COLUMN IF EXISTS unit_snapshot;
ALTER TABLE results DROP COLUMN IF EXISTS decimal_places_snapshot;
ALTER TABLE results DROP COLUMN IF EXISTS rounding_method_snapshot;
ALTER TABLE results DROP COLUMN IF EXISTS loq_snapshot;
ALTER TABLE results DROP COLUMN IF EXISTS uloq_snapshot;
ALTER TABLE results DROP COLUMN IF EXISTS reportable_value;
ALTER TABLE results DROP COLUMN IF EXISTS is_loq;
ALTER TABLE results DROP COLUMN IF EXISTS is_uloq;

ALTER TABLE sample_tests DROP COLUMN IF EXISTS analysis_id;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS spec_version_id;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS is_mandatory;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS added_reason;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS added_by;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS removed_reason;
ALTER TABLE sample_tests DROP COLUMN IF EXISTS removed_by;

ALTER TABLE samples DROP COLUMN IF EXISTS spec_version_id;
ALTER TABLE samples DROP COLUMN IF EXISTS sample_stage;
ALTER TABLE samples DROP COLUMN IF EXISTS template_id;
ALTER TABLE samples DROP COLUMN IF EXISTS receipt_check_status;
ALTER TABLE samples DROP COLUMN IF EXISTS receipt_check_notes;
ALTER TABLE samples DROP COLUMN IF EXISTS sampling_plan_schedule_id;
