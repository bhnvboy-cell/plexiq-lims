-- ====================================================================
-- Rollback: rollback_021_instruments_automation_vault.sql
-- Description: Rollback migration 021 Instruments Automation & Vault
-- ====================================================================

DROP TRIGGER IF EXISTS trg_vault_immutable ON instrument_raw_vault;
DROP FUNCTION IF EXISTS block_vault_mutation();
DROP TABLE IF EXISTS instrument_raw_vault CASCADE;
DROP TABLE IF EXISTS instrument_worklists CASCADE;
