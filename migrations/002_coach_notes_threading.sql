-- ----------------------------------------------------------------
-- DiaFitus — migration 002: coach-note threading & private notes
-- ----------------------------------------------------------------
-- Adds the columns needed for:
--   - private admin notes (visible only in /admin)
--   - replies (members can reply to coach notes, admin can reply back)
--   - per-check-in comments (admin attaches a comment to a specific
--     weekly_note or daily_log; member sees it inline)
--
-- Run AFTER schema.sql (and migration 001). Safe to re-run.
-- ----------------------------------------------------------------

ALTER TABLE `coach_notes`
  ADD COLUMN IF NOT EXISTS `is_private`  TINYINT(1)         NOT NULL DEFAULT 0 AFTER `kind`,
  ADD COLUMN IF NOT EXISTS `from_member` TINYINT(1)         NOT NULL DEFAULT 0 AFTER `is_private`,
  ADD COLUMN IF NOT EXISTS `target_type` VARCHAR(20)        NULL              AFTER `from_member`,
  ADD COLUMN IF NOT EXISTS `target_id`   BIGINT UNSIGNED    NULL              AFTER `target_type`,
  ADD COLUMN IF NOT EXISTS `parent_id`   BIGINT UNSIGNED    NULL              AFTER `target_id`,
  ADD INDEX IF NOT EXISTS `idx_target` (`target_type`, `target_id`),
  ADD INDEX IF NOT EXISTS `idx_parent` (`parent_id`);
