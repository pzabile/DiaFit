-- ----------------------------------------------------------------
-- DiaFitus — migration 003: plan duration per member
-- ----------------------------------------------------------------
-- Stores how many days the plan the member purchased lasts
-- (7, 28, or 84). Used to show correct progress on the dashboard.
-- Default 84 (12-week) for any existing paid members.
-- Safe to re-run.
-- ----------------------------------------------------------------

ALTER TABLE `leads`
  ADD COLUMN IF NOT EXISTS `plan_days` SMALLINT UNSIGNED NOT NULL DEFAULT 84 AFTER `started_at`;
