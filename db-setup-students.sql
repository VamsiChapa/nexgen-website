-- ================================================================
--  NEx-gEN — Student Tracker Database Setup
--  Run this in phpMyAdmin AFTER db-setup.sql (certificates/banners)
--  Database: u214786104_NexGen_Databas
-- ================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';   -- IST

-- ── 1. BATCHES ────────────────────────────────────────────────────
--  Each row is one class slot (e.g. 8:00 AM - 9:00 AM).
--  Add/remove rows from the admin panel; cron auto-detects them.
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `batches` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(60)   NOT NULL COMMENT 'Display label, e.g. 8:00 AM – 9:00 AM',
  `start_time` TIME          NOT NULL COMMENT 'Slot start, e.g. 08:00:00',
  `end_time`   TIME          NOT NULL COMMENT 'Slot end,   e.g. 09:00:00',
  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order` SMALLINT      NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed all 12 hourly slots 8 AM → 8 PM
INSERT IGNORE INTO `batches` (`name`, `start_time`, `end_time`, `sort_order`) VALUES
  ('8:00 AM – 9:00 AM',   '08:00:00', '09:00:00',  1),
  ('9:00 AM – 10:00 AM',  '09:00:00', '10:00:00',  2),
  ('10:00 AM – 11:00 AM', '10:00:00', '11:00:00',  3),
  ('11:00 AM – 12:00 PM', '11:00:00', '12:00:00',  4),
  ('12:00 PM – 1:00 PM',  '12:00:00', '13:00:00',  5),
  ('1:00 PM – 2:00 PM',   '13:00:00', '14:00:00',  6),
  ('2:00 PM – 3:00 PM',   '14:00:00', '15:00:00',  7),
  ('3:00 PM – 4:00 PM',   '15:00:00', '16:00:00',  8),
  ('4:00 PM – 5:00 PM',   '16:00:00', '17:00:00',  9),
  ('5:00 PM – 6:00 PM',   '17:00:00', '18:00:00', 10),
  ('6:00 PM – 7:00 PM',   '18:00:00', '19:00:00', 11),
  ('7:00 PM – 8:00 PM',   '19:00:00', '20:00:00', 12);

-- ── 2. STUDENTS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `students` (
  `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `student_name`     VARCHAR(100)     NOT NULL,
  `phone`            VARCHAR(15)      NOT NULL,
  `email`            VARCHAR(150)     DEFAULT NULL,
  `date_of_birth`    DATE             DEFAULT NULL,
  `gender`           ENUM('male','female','other') DEFAULT NULL,
  `address`          TEXT             DEFAULT NULL,
  `photo_url`        VARCHAR(512)     DEFAULT NULL,

  -- Enrollment
  `course`           VARCHAR(100)     NOT NULL,
  `batch_id`         INT UNSIGNED     NOT NULL
                     COMMENT 'FK → batches.id',
  `enrollment_date`  DATE             NOT NULL DEFAULT (CURDATE()),

  -- Parent / Guardian
  `parent_name`      VARCHAR(100)     DEFAULT NULL,
  `parent_phone`     VARCHAR(15)      DEFAULT NULL,
  `parent_email`     VARCHAR(150)     DEFAULT NULL,
  `parent_relation`  VARCHAR(50)      DEFAULT NULL,

  -- Biometric (optional — future scope)
  `biometric_id`     VARCHAR(50)      DEFAULT NULL
                     COMMENT 'ID stored in biometric device — optional',

  -- SMS / WhatsApp alerts
  `sms_enabled`      TINYINT(1)       NOT NULL DEFAULT 1
                     COMMENT '1=send absence alerts, 0=opted out',

  `status`           ENUM('active','inactive','completed','dropped')
                     NOT NULL DEFAULT 'active',
  `notes`            TEXT             DEFAULT NULL,
  `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_phone`      (`phone`),
  KEY        `idx_batch`     (`batch_id`),
  KEY        `idx_status`    (`status`),
  KEY        `idx_biometric` (`biometric_id`),
  CONSTRAINT `fk_student_batch`
    FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. ATTENDANCE ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `attendance` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `student_id`       INT UNSIGNED  NOT NULL,
  `attendance_date`  DATE          NOT NULL,
  `check_in_time`    TIME          DEFAULT NULL,
  `check_out_time`   TIME          DEFAULT NULL,
  `status`           ENUM('present','absent','late','leave')
                     NOT NULL DEFAULT 'present',
  `source`           ENUM('biometric','manual','csv_import','api')
                     NOT NULL DEFAULT 'manual',
  `notes`            VARCHAR(255)  DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_date` (`student_id`, `attendance_date`),
  KEY        `idx_date`        (`attendance_date`),
  KEY        `idx_status`      (`status`),
  CONSTRAINT `fk_att_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. SMS LOGS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sms_logs` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `student_id`        INT UNSIGNED  NOT NULL,
  `recipient_name`    VARCHAR(100)  NOT NULL,
  `phone`             VARCHAR(15)   NOT NULL,
  `message`           TEXT          NOT NULL,
  `type`              ENUM('absence','late','custom','test')
                      NOT NULL DEFAULT 'absence',
  `status`            ENUM('sent','failed','pending')
                      NOT NULL DEFAULT 'pending',
  `provider_response` TEXT          DEFAULT NULL,
  `sent_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_student_sent` (`student_id`, `sent_at`),
  CONSTRAINT `fk_sms_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. HOLIDAYS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `holidays` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `holiday_date` DATE          NOT NULL,
  `description`  VARCHAR(200)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. STUDENT LEAVES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `student_leaves` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED  NOT NULL,
  `leave_date` DATE          NOT NULL,
  `reason`     VARCHAR(255)  DEFAULT NULL,
  `approved`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_leave` (`student_id`, `leave_date`),
  CONSTRAINT `fk_leave_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed common holidays ──────────────────────────────────────────
INSERT IGNORE INTO `holidays` (`holiday_date`, `description`) VALUES
  ('2026-01-01', 'New Year\'s Day'),
  ('2026-01-14', 'Pongal / Makar Sankranti'),
  ('2026-01-26', 'Republic Day'),
  ('2026-03-30', 'Ugadi'),
  ('2026-04-14', 'Dr. Ambedkar Jayanti'),
  ('2026-08-15', 'Independence Day'),
  ('2026-10-02', 'Gandhi Jayanti'),
  ('2026-10-20', 'Dussehra'),
  ('2026-11-01', 'AP Formation Day'),
  ('2026-11-12', 'Diwali'),
  ('2026-12-25', 'Christmas');
