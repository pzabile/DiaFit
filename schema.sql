-- DiaFitus database schema (MySQL 5.7+ / MariaDB 10.2+)
-- Run this once in Hostinger -> hPanel -> Databases -> phpMyAdmin -> SQL tab.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `leads` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`           VARCHAR(190)    NOT NULL,
  `phone`           VARCHAR(40)     NULL,
  `first_name`      VARCHAR(80)     NULL,
  `answers_json`    LONGTEXT        NULL,
  `paid`            TINYINT(1)      NOT NULL DEFAULT 0,
  `stripe_customer` VARCHAR(80)     NULL,
  `stripe_sub`      VARCHAR(80)     NULL,
  `password_hash`   VARCHAR(255)    NULL,
  `password_reset_hash`    VARCHAR(64) NULL,
  `password_reset_expires` DATETIME    NULL,
  `dob`             DATE            NULL,
  `started_at`      DATE            NULL,
  `program_path`    VARCHAR(255)    NULL,
  `admin_notes`     LONGTEXT        NULL,
  `last_login_at`   DATETIME        NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_paid` (`paid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `weekly_notes` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id`       BIGINT UNSIGNED NOT NULL,
  `week_number`   SMALLINT UNSIGNED NOT NULL,
  `content`       LONGTEXT        NULL,
  `wins`          TEXT            NULL,
  `struggles`     TEXT            NULL,
  `avg_glucose`   SMALLINT UNSIGNED NULL,
  `weight_kg`     DECIMAL(5,1)    NULL,
  `energy_rating` TINYINT UNSIGNED NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead_week` (`lead_id`, `week_number`),
  CONSTRAINT `fk_weekly_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `daily_logs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id`      BIGINT UNSIGNED NOT NULL,
  `log_date`     DATE            NOT NULL,
  `feeling`      VARCHAR(20)     NULL,
  `trained`      VARCHAR(20)     NULL,
  `train_where`  VARCHAR(40)     NULL,
  `workout`      TEXT            NULL,
  `soreness`     TINYINT UNSIGNED NULL,
  `bs_before`    SMALLINT UNSIGNED NULL,
  `bs_after`     SMALLINT UNSIGNED NULL,
  `bs_trend`     VARCHAR(20)     NULL,
  `food_before`  VARCHAR(255)    NULL,
  `food_after`   VARCHAR(255)    NULL,
  `notes`        TEXT            NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead_date` (`lead_id`, `log_date`),
  CONSTRAINT `fk_log_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meal_photos` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id`    BIGINT UNSIGNED NOT NULL,
  `file_path`  VARCHAR(255)    NOT NULL,
  `meal_type`  VARCHAR(40)     NULL,
  `caption`    VARCHAR(500)    NULL,
  `eaten_at`   DATETIME        NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead` (`lead_id`),
  CONSTRAINT `fk_meal_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coach_notes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id`     BIGINT UNSIGNED NOT NULL,
  `week_number` SMALLINT UNSIGNED NULL,
  `body`        LONGTEXT        NOT NULL,
  `kind`        VARCHAR(40)     NOT NULL DEFAULT 'note',
  `is_private`  TINYINT(1)      NOT NULL DEFAULT 0,
  `from_member` TINYINT(1)      NOT NULL DEFAULT 0,
  `target_type` VARCHAR(20)     NULL,
  `target_id`   BIGINT UNSIGNED NULL,
  `parent_id`   BIGINT UNSIGNED NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead` (`lead_id`),
  KEY `idx_target` (`target_type`, `target_id`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `fk_cnote_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(80)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed an admin user. CHANGE THE PASSWORD before going live by running the
-- one-liner shown in INSTALL.txt to print a new bcrypt hash, then UPDATE here.
-- Default credentials below: admin / DiaFitusAdmin#2026
INSERT IGNORE INTO `admins` (`username`, `password_hash`)
VALUES ('admin', '$2y$12$d9sW7ytmBwUcoi.GmLFyKuAwmouOMgtXQ3hls5pgiWncPbBsabdZ2');
