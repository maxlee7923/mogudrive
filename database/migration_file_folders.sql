CREATE TABLE IF NOT EXISTS file_folders (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  storage_id BIGINT NOT NULL,
  folder_path VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_storage_folder (storage_id, folder_path),
  INDEX idx_storage_folder (storage_id, folder_path),
  CONSTRAINT fk_folder_storage FOREIGN KEY (storage_id) REFERENCES storage_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
