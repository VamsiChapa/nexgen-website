-- ================================================================
--  NEx-gEN — Migration: Make SMS optional per-student
--  Run this in phpMyAdmin if you already ran db-setup-students.sql
--  If running fresh, db-setup-students.sql already includes this.
-- ================================================================

ALTER TABLE `students`
  ADD COLUMN `sms_enabled` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = send absence alerts, 0 = opted out'
  AFTER `biometric_id`;
