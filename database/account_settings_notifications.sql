-- Migración aditiva, compatible con MariaDB 10.4. Puede ejecutarse de nuevo.
-- users.phone ya existe (VARCHAR(30)); no reducir ni corregir datos históricos.
USE devioz_parking;

CREATE TABLE IF NOT EXISTS user_preferences (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 reservations TINYINT(1) NOT NULL DEFAULT 1,
 payments TINYINT(1) NOT NULL DEFAULT 1,
 pending_actions TINYINT(1) NOT NULL DEFAULT 1,
 system_notices TINYINT(1) NOT NULL DEFAULT 1,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT chk_preferences_flags CHECK (reservations IN (0,1) AND payments IN (0,1) AND pending_actions IN (0,1) AND system_notices IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 type ENUM('reservations','payments','pending_actions','system_notices') NOT NULL,
 title VARCHAR(150) NOT NULL,
 message VARCHAR(1000) NOT NULL,
 action_url VARCHAR(255) NULL,
 event_key VARCHAR(190) NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 read_at DATETIME NULL,
 INDEX idx_notifications_user_read (user_id,is_read,id),
 UNIQUE KEY uq_notifications_event (user_id,event_key),
 CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT chk_notifications_read CHECK (is_read IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
