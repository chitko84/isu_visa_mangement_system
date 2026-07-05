CREATE TABLE IF NOT EXISTS password_resets (
  reset_id INT AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('student','staff') NOT NULL,
  user_id INT NOT NULL,
  email VARCHAR(150) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_resets_token (token_hash),
  INDEX idx_password_resets_user (user_type, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT DEFAULT NULL,
  actor_role VARCHAR(50) DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(100) DEFAULT NULL,
  entity_id INT DEFAULT NULL,
  details TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_actor (actor_id, actor_role),
  INDEX idx_audit_action (action),
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT DEFAULT NULL,
  staff_id INT DEFAULT NULL,
  notification_type VARCHAR(50) DEFAULT 'general',
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_student (student_id, is_read),
  INDEX idx_notifications_staff (staff_id, is_read),
  INDEX idx_notifications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS staff_id INT DEFAULT NULL AFTER student_id,
  ADD COLUMN IF NOT EXISTS notification_type VARCHAR(50) DEFAULT 'general' AFTER staff_id;
