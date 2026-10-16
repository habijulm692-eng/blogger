-- Migration 004: Blueprint-driven Idea Generator
-- Run once. Safe to re-run (IF NOT EXISTS / IGNORE).

-- Add blueprint text column to source_websites
ALTER TABLE source_websites
    ADD COLUMN IF NOT EXISTS blueprint TEXT NULL COMMENT 'User-defined content strategy blueprint for AI idea generation',
    ADD COLUMN IF NOT EXISTS blueprint_updated_at DATETIME NULL;

-- Standalone blueprint ideas table (ideas generated purely from blueprint, not from fetched source items)
CREATE TABLE IF NOT EXISTS blueprint_ideas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id      INT UNSIGNED NOT NULL,
    topic         VARCHAR(500)  NOT NULL,
    primary_keyword VARCHAR(200) NULL,
    tone          VARCHAR(100)  NULL,
    tags          TEXT          NULL,  -- JSON array
    angle         TEXT          NULL,
    category      VARCHAR(100)  NULL,
    language      VARCHAR(10)   NOT NULL DEFAULT 'bn',
    status        ENUM('pending','used','dismissed') NOT NULL DEFAULT 'pending',
    generated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_status (admin_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
