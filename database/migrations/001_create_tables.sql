-- AI Blogger Database Schema
-- MySQL 8.0+
-- Charset: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table: admins
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
    `password` VARCHAR(255) NOT NULL,
    `remember_token` VARCHAR(100) DEFAULT NULL,
    `last_login_at` DATETIME DEFAULT NULL,
    `last_login_ip` VARCHAR(45) DEFAULT NULL,
    `login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admins_email` (`email`),
    UNIQUE KEY `uk_admins_username` (`username`),
    KEY `idx_admins_remember` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: posts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `topic` VARCHAR(500) NOT NULL,
    `primary_keyword` VARCHAR(200) DEFAULT NULL,
    `secondary_keywords` JSON DEFAULT NULL,
    `title` VARCHAR(500) DEFAULT NULL,
    `meta_description` VARCHAR(500) DEFAULT NULL,
    `excerpt` TEXT DEFAULT NULL,
    `content_html` LONGTEXT DEFAULT NULL,
    `tags` JSON DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `language` VARCHAR(10) NOT NULL DEFAULT 'bn',
    `word_count` INT UNSIGNED DEFAULT 1000,
    `target_audience` VARCHAR(200) DEFAULT NULL,
    `writing_tone` VARCHAR(100) DEFAULT NULL,
    `status` ENUM(
        'DRAFT',
        'GENERATING',
        'GENERATED',
        'AI_REVIEWING',
        'HUMAN_REVIEW',
        'REJECTED',
        'APPROVED',
        'SCHEDULED',
        'PUBLISHING',
        'PUBLISHED',
        'FAILED'
    ) NOT NULL DEFAULT 'DRAFT',
    `ai_score` DECIMAL(3,1) DEFAULT NULL,
    `ai_provider_used` VARCHAR(50) DEFAULT NULL,
    `ai_model_used` VARCHAR(100) DEFAULT NULL,
    `generation_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `blogger_post_id` VARCHAR(100) DEFAULT NULL,
    `blogger_url` VARCHAR(500) DEFAULT NULL,
    `publish_attempt_id` VARCHAR(64) DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `error_code` VARCHAR(50) DEFAULT NULL,
    `retry_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_attempt_at` DATETIME DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `approved_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `approved_at` DATETIME DEFAULT NULL,
    `scheduled_at` DATETIME DEFAULT NULL,
    `published_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_posts_status` (`status`),
    KEY `idx_posts_scheduled_at` (`scheduled_at`),
    KEY `idx_posts_created_at` (`created_at`),
    KEY `idx_posts_blogger_post_id` (`blogger_post_id`),
    KEY `idx_posts_publish_attempt` (`publish_attempt_id`),
    KEY `idx_posts_created_by` (`created_by`),
    CONSTRAINT `fk_posts_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_posts_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: post_reviews
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `post_reviews` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `provider` VARCHAR(50) NOT NULL,
    `model` VARCHAR(100) DEFAULT NULL,
    `score` DECIMAL(3,1) DEFAULT NULL,
    `grammar_score` DECIMAL(3,1) DEFAULT NULL,
    `seo_score` DECIMAL(3,1) DEFAULT NULL,
    `readability_score` DECIMAL(3,1) DEFAULT NULL,
    `structure_score` DECIMAL(3,1) DEFAULT NULL,
    `problems` JSON DEFAULT NULL,
    `suggestions` JSON DEFAULT NULL,
    `required_changes` JSON DEFAULT NULL,
    `verdict` ENUM('APPROVE', 'NEEDS_EDIT') DEFAULT NULL,
    `raw_response` LONGTEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_post_reviews_post_id` (`post_id`),
    CONSTRAINT `fk_post_reviews_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: post_status_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `post_status_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(30) DEFAULT NULL,
    `to_status` VARCHAR(30) NOT NULL,
    `changed_by` INT UNSIGNED DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_logs_post_id` (`post_id`),
    KEY `idx_status_logs_created_at` (`created_at`),
    CONSTRAINT `fk_status_logs_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_status_logs_admin` FOREIGN KEY (`changed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: scheduled_posts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scheduled_posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `scheduled_at` DATETIME NOT NULL,
    `timezone` VARCHAR(50) NOT NULL DEFAULT 'Asia/Dhaka',
    `status` ENUM('PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_attempt_at` DATETIME DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `locked_at` DATETIME DEFAULT NULL,
    `locked_by` VARCHAR(64) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_scheduled_posts_post_id` (`post_id`),
    KEY `idx_scheduled_status_time` (`status`, `scheduled_at`),
    KEY `idx_scheduled_locked` (`locked_at`),
    CONSTRAINT `fk_scheduled_posts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: ai_providers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_providers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `priority` INT UNSIGNED NOT NULL DEFAULT 10,
    `model` VARCHAR(100) DEFAULT NULL,
    `timeout` INT UNSIGNED NOT NULL DEFAULT 60,
    `max_retries` INT UNSIGNED NOT NULL DEFAULT 1,
    `config` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ai_providers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: ai_usage_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider` VARCHAR(50) NOT NULL,
    `model` VARCHAR(100) DEFAULT NULL,
    `operation` VARCHAR(50) NOT NULL,
    `post_id` INT UNSIGNED DEFAULT NULL,
    `request_time` DATETIME NOT NULL,
    `response_time` DATETIME DEFAULT NULL,
    `duration_ms` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('SUCCESS', 'FAILED', 'TIMEOUT', 'INVALID_RESPONSE') NOT NULL,
    `http_status` INT DEFAULT NULL,
    `error_code` VARCHAR(50) DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `prompt_tokens` INT UNSIGNED DEFAULT NULL,
    `completion_tokens` INT UNSIGNED DEFAULT NULL,
    `total_tokens` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ai_usage_provider` (`provider`),
    KEY `idx_ai_usage_operation` (`operation`),
    KEY `idx_ai_usage_status` (`status`),
    KEY `idx_ai_usage_created_at` (`created_at`),
    KEY `idx_ai_usage_post_id` (`post_id`),
    CONSTRAINT `fk_ai_usage_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: blogger_connections
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blogger_connections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` INT UNSIGNED NOT NULL,
    `google_account_email` VARCHAR(150) DEFAULT NULL,
    `access_token` TEXT NOT NULL,
    `refresh_token` TEXT DEFAULT NULL,
    `token_expires_at` DATETIME DEFAULT NULL,
    `blog_id` VARCHAR(100) DEFAULT NULL,
    `blog_name` VARCHAR(200) DEFAULT NULL,
    `blog_url` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_used_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_blogger_admin` (`admin_id`),
    KEY `idx_blogger_active` (`is_active`),
    CONSTRAINT `fk_blogger_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: system_settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` ENUM('string', 'integer', 'boolean', 'json', 'encrypted') NOT NULL DEFAULT 'string',
    `group_name` VARCHAR(50) DEFAULT 'general',
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: activity_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `metadata` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_admin` (`admin_id`),
    KEY `idx_activity_action` (`action`),
    KEY `idx_activity_entity` (`entity_type`, `entity_id`),
    KEY `idx_activity_created_at` (`created_at`),
    CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: login_attempts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL,
    `email_or_username` VARCHAR(150) DEFAULT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_ip_time` (`ip_address`, `attempted_at`),
    KEY `idx_login_email` (`email_or_username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
