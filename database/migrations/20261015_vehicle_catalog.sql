-- Additive catalog only; does not modify vehicles or historical records.
CREATE TABLE IF NOT EXISTS vehicle_catalog_makes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_slug VARCHAR(100) NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_catalog_make_slug (source_slug),
    KEY idx_catalog_make_search (is_active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_catalog_models (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    make_id BIGINT UNSIGNED NOT NULL,
    source_slug VARCHAR(100) NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    source_vehicle_type VARCHAR(20) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_catalog_model_slug (make_id, source_slug),
    KEY idx_catalog_model_search (make_id, is_active, name),
    CONSTRAINT fk_catalog_model_make FOREIGN KEY (make_id)
        REFERENCES vehicle_catalog_makes (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
