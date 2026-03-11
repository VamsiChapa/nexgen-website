-- ================================================================
-- NEx-gEN — Banners Table Setup
-- Run this in phpMyAdmin → SQL tab (same database)
-- ================================================================

USE `u214786104_NexGen_Databas`;

CREATE TABLE IF NOT EXISTS `banners` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `badge_text`    VARCHAR(80)   DEFAULT NULL   COMMENT 'Small top badge e.g. "🌸 Women''s Day Special"',
  `title`         VARCHAR(120)  NOT NULL       COMMENT 'Main heading line 1',
  `title_span`    VARCHAR(80)   DEFAULT NULL   COMMENT 'Highlighted (coloured) word/phrase in title',
  `subtitle`      VARCHAR(220)  DEFAULT NULL   COMMENT 'Subtext below heading',
  `image_url`     VARCHAR(512)  DEFAULT NULL   COMMENT 'Background image path or URL',
  `bg_color`      VARCHAR(30)   DEFAULT '#0f4e8a' COMMENT 'Fallback bg colour if no image',
  `btn1_text`     VARCHAR(60)   DEFAULT 'Explore Courses',
  `btn1_link`     VARCHAR(200)  DEFAULT '#courses',
  `btn2_text`     VARCHAR(60)   DEFAULT NULL,
  `btn2_link`     VARCHAR(200)  DEFAULT '#contact',
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `display_from`  DATE          DEFAULT NULL   COMMENT 'Show from this date (NULL = always)',
  `display_until` DATE          DEFAULT NULL   COMMENT 'Hide after this date (NULL = always)',
  `sort_order`    SMALLINT      NOT NULL DEFAULT 0 COMMENT 'Lower = shows first',
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_active_dates` (`is_active`, `display_from`, `display_until`),
  INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Promotional banners shown in the hero carousel';

-- ── Sample banners (delete before go-live) ──────────────────────
/*
INSERT INTO `banners` (badge_text, title, title_span, subtitle, bg_color, btn1_text, btn1_link, btn2_text, btn2_link, is_active, sort_order)
VALUES
  ('🌸 Women''s Day Special', 'Empowering Women', 'Through Education', 'Special discounts on all courses this Women''s Day!', '#8e1a6e', 'Explore Courses', '#courses', 'Enquire Now', '#contact', 1, 0),
  ('🎉 Ugadi Wishes', 'New Year, New Skills', 'Start Today', 'Enrol this Ugadi and build your IT career', '#1a6eb5', 'View Courses', '#courses', NULL, NULL, 1, 1);
*/

SHOW TABLES;
DESCRIBE `banners`;
