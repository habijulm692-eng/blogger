-- Seed data for AI Blogger

SET NAMES utf8mb4;

-- Default Admin (password: Admin@12345)
-- Hash generated with password_hash('Admin@12345', PASSWORD_BCRYPT)
INSERT INTO `admins` (`name`, `email`, `username`, `password`, `is_active`) VALUES
('Administrator', 'admin@example.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Note: The above hash is a placeholder. Run the seeder PHP script to generate a real hash.
-- Default password will be set via PHP seeder for security.

-- AI Providers (priority order)
INSERT INTO `ai_providers` (`name`, `display_name`, `is_enabled`, `priority`, `model`, `timeout`, `max_retries`) VALUES
('gemini_primary', 'Google Gemini Flash 3.5', 1, 1, 'gemini-3.5-flash', 180, 1),
('gemini_secondary', 'Google Gemini Flash 3', 1, 2, 'gemini-3-flash', 180, 1),
('sambanova', 'SambaNova', 1, 3, 'Meta-Llama-3.3-70B-Instruct', 120, 1),
('xkero', 'Xkero (Qwen)', 1, 4, 'Qwen/Qwen2.5-72B-Instruct', 120, 1),
('apinex', 'Apinex', 1, 5, 'gpt-4o-mini', 120, 1),
('groq', 'Groq', 1, 6, 'llama-3.1-8b-instant', 90, 1);

-- System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `group_name`, `description`) VALUES
('site_name', 'AI Blogger', 'string', 'general', 'Application name'),
('timezone', 'Asia/Dhaka', 'string', 'general', 'Default timezone'),
('default_language', 'bn', 'string', 'general', 'Default content language'),
('default_word_count', '1000', 'integer', 'general', 'Default target word count'),
('default_audience', 'General Bangladesh Readers', 'string', 'general', 'Default target audience'),
('default_tone', 'Professional + Easy', 'string', 'general', 'Default writing tone'),
('ai_temperature', '0.7', 'string', 'ai', 'AI temperature setting'),
('ai_max_tokens', '4096', 'integer', 'ai', 'Max tokens for AI generation'),
('ai_timeout', '60', 'integer', 'ai', 'AI request timeout in seconds'),
('publish_frequency', 'daily', 'string', 'publishing', 'Publishing frequency: daily, every_2_days, custom'),
('default_schedule_time', '10:00', 'string', 'publishing', 'Default time to schedule posts'),
('publish_retry_limit', '3', 'integer', 'publishing', 'Max publish retry attempts'),
('auto_approval_mode', '0', 'boolean', 'publishing', 'Auto approve AI content (OFF by default)'),
('login_max_attempts', '5', 'integer', 'security', 'Max failed login attempts'),
('login_lockout_minutes', '15', 'integer', 'security', 'Lockout duration in minutes'),
('log_retention_days', '30', 'integer', 'maintenance', 'Days to retain logs');
