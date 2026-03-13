-- ================================================================
-- NEx-gEN — Enquiry System Migration
-- Safe to run on an existing live database.
-- Run in phpMyAdmin → SQL tab.
-- ================================================================

-- ── 1. Create enquiries table (skips if already exists) ──────────
CREATE TABLE IF NOT EXISTS `enquiries` (
  `id`                       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `enquiry_number`           VARCHAR(20)   DEFAULT NULL,
  `name`                     VARCHAR(100)  NOT NULL,
  `phone`                    VARCHAR(15)   NOT NULL,
  `email`                    VARCHAR(100)  DEFAULT NULL,
  `courses_interested`       TEXT          DEFAULT NULL,
  `preferred_batch`          VARCHAR(100)  DEFAULT NULL,
  `source` ENUM('walk-in','phone-call','referral','website','social-media','other')
    NOT NULL DEFAULT 'walk-in',
  `message`                  TEXT          DEFAULT NULL,
  `status` ENUM('new','contacted','interested','enrolled','not-interested','dropped')
    NOT NULL DEFAULT 'new',
  `follow_up_date`           DATE          DEFAULT NULL,
  `converted_to_student_id`  INT UNSIGNED  DEFAULT NULL,
  `enquiry_date`             DATE          NOT NULL,
  `created_at`               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enquiry_number` (`enquiry_number`),
  KEY `idx_status`            (`status`),
  KEY `idx_phone`             (`phone`),
  KEY `idx_follow_up_date`    (`follow_up_date`),
  KEY `idx_enquiry_date`      (`enquiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Add source_enquiry_id column to students (if missing) ─────
-- Note: ADD COLUMN IF NOT EXISTS is MySQL 8.0+.
-- If your server is MySQL 5.7, run this only once manually:
--   ALTER TABLE `students` ADD COLUMN `source_enquiry_id` INT UNSIGNED DEFAULT NULL AFTER `notes`;
ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `source_enquiry_id` INT UNSIGNED DEFAULT NULL
    COMMENT 'FK → enquiries.id'
    AFTER `notes`;
