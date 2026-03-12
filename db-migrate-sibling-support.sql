-- ================================================================
--  NEx-gEN — Migration: Sibling Support
--  Run this in phpMyAdmin if students table already exists.
--  If setting up fresh, db-setup-students.sql already includes this.
-- ================================================================

-- 1. Add admission_number as the new unique identifier
--    (auto-generated in PHP, format: NXG2026001)
ALTER TABLE `students`
  ADD COLUMN `admission_number` VARCHAR(20) DEFAULT NULL
    COMMENT 'Auto-generated: NXG + Year + 4-digit seq, e.g. NXG2026001'
  AFTER `id`;

-- 2. Back-fill existing rows with a placeholder admission number
--    (the admin page will show these and they can be updated)
SET @counter = 0;
UPDATE `students`
SET `admission_number` = CONCAT('NXG', YEAR(CURDATE()), LPAD((@counter := @counter + 1), 4, '0'))
ORDER BY `id` ASC;

-- 3. Now make admission_number UNIQUE
ALTER TABLE `students`
  ADD UNIQUE KEY `uq_admission_number` (`admission_number`);

-- 4. Remove the UNIQUE constraint from phone
--    (siblings may share a phone; admission_number is now the unique ID)
ALTER TABLE `students`
  DROP INDEX `uq_phone`;

-- Note: The phone column stays — it's still useful for contact.
-- We just no longer enforce uniqueness on it.
