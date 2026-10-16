-- Run once on existing DB. Ignore errors if column/table already exists.

CREATE TABLE IF NOT EXISTS source_websites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  url VARCHAR(500) NOT NULL,
  rss_url VARCHAR(500) NULL,
  site_type ENUM('blog','news','other') NOT NULL DEFAULT 'blog',
  category VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_fetched_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS source_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_id INT UNSIGNED NOT NULL,
  title VARCHAR(500) NOT NULL,
  url VARCHAR(700) NOT NULL,
  summary TEXT NULL,
  published_at DATETIME NULL,
  content_hash VARCHAR(64) NOT NULL,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_source_hash (source_id, content_hash),
  INDEX idx_source (source_id),
  CONSTRAINT fk_source_items_source FOREIGN KEY (source_id) REFERENCES source_websites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS topic_suggestions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_item_id INT UNSIGNED NULL,
  title VARCHAR(500) NOT NULL,
  angle TEXT NULL,
  primary_keyword VARCHAR(200) NULL,
  category VARCHAR(100) NULL,
  status ENUM('pending','accepted','rejected','used') NOT NULL DEFAULT 'pending',
  created_post_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  role ENUM('user','assistant','system') NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_created (admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: add role column if missing (run manually if error)
-- ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER username;
