-- Additive migration. Run with the migration/maintenance account, never through a browser.
-- Existing recordings and application data are not modified.
CREATE TABLE IF NOT EXISTS camera_recordings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    parking_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    source_kind ENUM('webcam') NOT NULL DEFAULT 'webcam',
    format ENUM('webm', 'mp4') NOT NULL,
    state ENUM('pending', 'ready') NOT NULL DEFAULT 'pending',
    duration_ms INT UNSIGNED NULL,
    byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    started_at DATETIME(3) NOT NULL,
    ended_at DATETIME(3) NULL,
    created_at DATETIME(3) NOT NULL,
    updated_at DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_camera_recordings_public (public_id),
    KEY idx_camera_recordings_parking_ready (parking_id, state, started_at, id),
    KEY idx_camera_recordings_creator_pending (created_by, state, created_at),
    CONSTRAINT fk_camera_recordings_parking FOREIGN KEY (parking_id) REFERENCES parkings (id) ON DELETE RESTRICT,
    CONSTRAINT fk_camera_recordings_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
