-- Aditiva. Respaldar users antes de aplicar. No borra ni reemplaza cuentas.
ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0;
CREATE TABLE password_resets (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL,
    password_fingerprint CHAR(64) NOT NULL,
    encrypted_token TEXT NULL,
    expires_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    delivery_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
