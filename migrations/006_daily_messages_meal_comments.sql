-- migration 006: daily coach messages per member + meal photo admin comments
CREATE TABLE IF NOT EXISTS `member_daily_messages` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id`      BIGINT UNSIGNED NOT NULL,
  `message_date` DATE            NOT NULL,
  `body`         TEXT            NOT NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lead_date` (`lead_id`, `message_date`),
  CONSTRAINT `fk_dailymsg_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `meal_photos`
  ADD COLUMN IF NOT EXISTS `admin_comment` TEXT NULL AFTER `caption`;
