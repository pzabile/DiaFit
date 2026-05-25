-- migration 007: dismissed_at for inbox "mark resolved" feature
ALTER TABLE `leads`
  ADD COLUMN IF NOT EXISTS `coach_dismissed_at` DATETIME NULL AFTER `updated_at`;
