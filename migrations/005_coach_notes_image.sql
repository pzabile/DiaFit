-- migration 005: add image_path column to coach_notes for chat attachments
ALTER TABLE `coach_notes`
  ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(500) NULL AFTER `body`;
