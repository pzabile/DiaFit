-- ----------------------------------------------------------------
-- DiaFitus — migration 001: password reset
-- ----------------------------------------------------------------
-- Adds the two columns the /forgot and /reset flow needs.
-- Run AFTER schema.sql, in Hostinger -> phpMyAdmin -> SQL tab.
-- Safe to run multiple times (IF NOT EXISTS).
-- ----------------------------------------------------------------

ALTER TABLE `leads`
  ADD COLUMN IF NOT EXISTS `password_reset_hash`    VARCHAR(64) NULL AFTER `password_hash`,
  ADD COLUMN IF NOT EXISTS `password_reset_expires` DATETIME    NULL AFTER `password_reset_hash`;
