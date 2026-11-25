-- ====================================================================
-- Rollback: rollback_020_quality_regulated_lifecycle.sql
-- Description: Rollback migration 020 Quality & Regulated Lifecycle
-- ====================================================================

DROP TABLE IF EXISTS uncertainty_components CASCADE;
DROP TABLE IF EXISTS uncertainty_budgets CASCADE;
DROP TABLE IF EXISTS calibration_impact_assessments CASCADE;
DROP TABLE IF EXISTS instrument_qualifications CASCADE;
DROP TABLE IF EXISTS training_qualifications CASCADE;
DROP TABLE IF EXISTS sop_controlled_copies CASCADE;
DROP TABLE IF EXISTS sops CASCADE;
DROP TABLE IF EXISTS fmea_risk_assessments CASCADE;
DROP TABLE IF EXISTS customer_complaints CASCADE;
DROP TABLE IF EXISTS change_controls CASCADE;
