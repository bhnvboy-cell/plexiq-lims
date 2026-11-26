-- Rollback Migration 027: Two Kinds of Batch
DROP VIEW IF EXISTS v_production_lots_legacy;
ALTER TABLE samples DROP COLUMN IF EXISTS production_lot_id;
DROP TABLE IF EXISTS lab_batch_items CASCADE;
DROP TABLE IF EXISTS lab_batches CASCADE;
DROP TABLE IF EXISTS production_lots CASCADE;
