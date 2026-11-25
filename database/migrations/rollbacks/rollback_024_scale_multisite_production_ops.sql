-- Rollback Migration: 024_scale_multisite_production_ops.sql
DROP TABLE IF EXISTS archive_records CASCADE;
DROP TABLE IF EXISTS job_queues CASCADE;
ALTER TABLE instruments DROP COLUMN IF EXISTS site_id;
ALTER TABLE samples DROP COLUMN IF EXISTS site_id;
ALTER TABLE users DROP COLUMN IF EXISTS site_id;
DROP TABLE IF EXISTS sites CASCADE;
