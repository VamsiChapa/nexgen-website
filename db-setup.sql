-- ================================================================
-- NEx-gEN School of Computers — Database Setup
-- Database: u214786104_NexGen_Databas
-- ================================================================
-- HOW TO RUN THIS:
--   Option 1 (phpMyAdmin — RECOMMENDED):
--     1. Log in to hPanel → Databases → phpMyAdmin
--     2. Select database "u214786104_NexGen_Databas" from left sidebar
--     3. Click "SQL" tab at the top
--     4. Paste the contents of this file and click "Go"
--
--   Option 2 (MySQL CLI on Hostinger SSH):
--     mysql -u u214786104_superadmin -p u214786104_NexGen_Databas < db-setup.sql
-- ================================================================

USE `u214786104_NexGen_Databas`;

-- ── CERTIFICATES TABLE ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `certificates` (
  `id`                 INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `certificate_number` VARCHAR(60)    NOT NULL,
  `student_name`       VARCHAR(100)   NOT NULL,
  `course_name`        VARCHAR(100)   DEFAULT NULL,
  `issue_date`         DATE           DEFAULT NULL,
  `certificate_url`    VARCHAR(512)   DEFAULT NULL   COMMENT 'Relative path or full URL to certificate image',
  `is_active`          TINYINT(1)     NOT NULL DEFAULT 1 COMMENT '1 = active/visible, 0 = inactive/hidden',
  `created_at`         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE  KEY `uq_cert_number` (`certificate_number`),
  INDEX   `idx_student_name`   (`student_name`(20)),
  INDEX   `idx_is_active`      (`is_active`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Student certificates issued by NEx-gEN School of Computers';

-- ── SAMPLE DATA (delete before going live) ──────────────────────
-- Uncomment the lines below to insert a test certificate
-- you can use to verify the system is working.
/*
INSERT INTO `certificates`
  (certificate_number, student_name, course_name, issue_date, certificate_url, is_active)
VALUES
  ('NGN-2024-0001', 'Venkat Ramana', 'PGDCA',       '2024-03-15', NULL, 1),
  ('NGN-2024-0002', 'Padmavathi D', 'Tally Prime',   '2024-04-20', NULL, 1),
  ('NGN-2024-0003', 'Ramakrishna S', 'Python',        '2024-05-10', NULL, 1);
*/

-- ── VERIFY THE TABLE WAS CREATED ────────────────────────────────
SHOW TABLES;
DESCRIBE `certificates`;
