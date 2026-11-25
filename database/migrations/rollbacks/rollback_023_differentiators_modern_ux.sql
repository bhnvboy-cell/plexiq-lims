-- Rollback Migration: 023_differentiators_modern_ux.sql
DROP TABLE IF EXISTS validation_requirements CASCADE;
DROP TABLE IF EXISTS ai_quality_suggestions CASCADE;
DROP TABLE IF EXISTS environment_promotions CASCADE;
DROP TABLE IF EXISTS dynamic_form_schemas CASCADE;
