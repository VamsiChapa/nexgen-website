-- ================================================================
-- NEx-gEN — Enquiry System Schema
-- Run AFTER db-setup-students.sql in phpMyAdmin.
--
-- For EXISTING deployments, run db-migrate-enquiries.sql instead
-- (it is safe to run on top of a live database).
-- ================================================================

-- ── Enquiries table ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `enquiries` (
  `id`                       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `enquiry_number`           VARCHAR(20)   DEFAULT NULL   COMMENT 'Auto-generated: ENQYYYYxxxx e.g. ENQ20260001',
  `name`                     VARCHAR(100)  NOT NULL,
  `phone`                    VARCHAR(15)   NOT NULL,
  `email`                    VARCHAR(100)  DEFAULT NULL,
  `courses_interested`       TEXT          DEFAULT NULL   COMMENT 'Comma-separated course names',
  `preferred_batch`          VARCHAR(100)  DEFAULT NULL,
  `source` ENUM(
    'walk-in','phone-call','referral','website','social-media','other'
  ) NOT NULL DEFAULT 'walk-in',
  `message`                  TEXT          DEFAULT NULL,
  `status` ENUM(
    'new','contacted','interested','enrolled','not-interested','dropped'
  ) NOT NULL DEFAULT 'new',
  `follow_up_date`           DATE          DEFAULT NULL   COMMENT 'Next scheduled contact date',
  `converted_to_student_id`  INT UNSIGNED  DEFAULT NULL   COMMENT 'FK → students.id once enrolled',
  `enquiry_date`             DATE          NOT NULL,
  `created_at`               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enquiry_number`  (`enquiry_number`),
  KEY `idx_status`                (`status`),
  KEY `idx_phone`                 (`phone`),
  KEY `idx_follow_up_date`        (`follow_up_date`),
  KEY `idx_enquiry_date`          (`enquiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Add source_enquiry_id to students (bidirectional link) ───────
ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `source_enquiry_id` INT UNSIGNED DEFAULT NULL
    COMMENT 'FK → enquiries.id — NULL for direct walk-in registrations'
    AFTER `notes`;
