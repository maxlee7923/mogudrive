CREATE TABLE IF NOT EXISTS users (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(120) PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS storage_locations (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  driver VARCHAR(20) NOT NULL,
  config_json JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS upload_sessions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  upload_token VARCHAR(64) NOT NULL UNIQUE,
  original_name VARCHAR(255) NOT NULL,
  total_size BIGINT NOT NULL,
  total_chunks INT NOT NULL,
  storage_id BIGINT NOT NULL,
  file_sha256 VARCHAR(64) NULL,
  expected_md5 VARCHAR(32) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'uploading',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (storage_id),
  CONSTRAINT fk_upload_storage FOREIGN KEY (storage_id) REFERENCES storage_locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS upload_chunks (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  upload_session_id BIGINT NOT NULL,
  chunk_index INT NOT NULL,
  chunk_size BIGINT NOT NULL,
  etag VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_upload_chunk (upload_session_id, chunk_index),
  CONSTRAINT fk_chunk_session FOREIGN KEY (upload_session_id) REFERENCES upload_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS files (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  storage_id BIGINT NOT NULL,
  object_key VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  size BIGINT NOT NULL,
  sha256 VARCHAR(64) NULL,
  md5 VARCHAR(32) NULL,
  folder_path VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  INDEX (storage_id),
  INDEX (folder_path),
  CONSTRAINT fk_file_storage FOREIGN KEY (storage_id) REFERENCES storage_locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS file_folders (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  storage_id BIGINT NOT NULL,
  folder_path VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_storage_folder (storage_id, folder_path),
  INDEX idx_storage_folder (storage_id, folder_path),
  CONSTRAINT fk_folder_storage FOREIGN KEY (storage_id) REFERENCES storage_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shares (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(255) NULL,
  password_hash VARCHAR(255) NULL,
  expires_at DATETIME NULL,
  allow_folder TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS share_items (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  share_id BIGINT NOT NULL,
  file_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX (share_id),
  CONSTRAINT fk_share_item_share FOREIGN KEY (share_id) REFERENCES shares(id) ON DELETE CASCADE,
  CONSTRAINT fk_share_item_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
