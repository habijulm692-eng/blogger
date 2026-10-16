-- Run once on your database (phpMyAdmin or MySQL CLI)
ALTER TABLE chat_messages
  ADD COLUMN IF NOT EXISTS conversation_id VARCHAR(36) NULL AFTER admin_id,
  ADD COLUMN IF NOT EXISTS provider VARCHAR(50) NULL AFTER message;

-- MySQL < 8.0.12 may not support IF NOT EXISTS on columns; use:
-- ALTER TABLE chat_messages ADD COLUMN conversation_id VARCHAR(36) NULL AFTER admin_id;
-- ALTER TABLE chat_messages ADD COLUMN provider VARCHAR(50) NULL AFTER message;

ALTER TABLE chat_messages
  ADD INDEX idx_chat_conv (admin_id, conversation_id);
