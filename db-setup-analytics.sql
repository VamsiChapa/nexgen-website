-- ================================================================
-- NEx-gEN — Page Hit Analytics Schema
-- Run in phpMyAdmin → SQL tab.
-- ================================================================

CREATE TABLE IF NOT EXISTS `page_hits` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_path`    VARCHAR(255)    NOT NULL               COMMENT 'URL path, e.g. /index.html',
  `ip_address`   VARCHAR(45)     DEFAULT NULL           COMMENT 'IPv4 or IPv6 (Cloudflare-aware)',
  `user_agent`   VARCHAR(512)    DEFAULT NULL,
  `referrer`     VARCHAR(500)    DEFAULT NULL           COMMENT 'Where the visitor came from',
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_page_path`  (`page_path`(64)),
  KEY `idx_ip`         (`ip_address`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Optional: auto-purge rows older than 90 days (via event) ────
-- Enable if your Hostinger plan supports MySQL events:
-- CREATE EVENT IF NOT EXISTS `purge_old_hits`
--   ON SCHEDULE EVERY 1 DAY
--   DO DELETE FROM `page_hits` WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
