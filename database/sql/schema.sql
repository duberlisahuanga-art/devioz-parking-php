CREATE DATABASE IF NOT EXISTS devioz_parking
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE devioz_parking;

-- =====================================================
-- USUARIOS
-- =====================================================

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,

    role ENUM('admin', 'driver') NOT NULL DEFAULT 'driver',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',

    terms_accepted_at DATETIME NULL,

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =====================================================
-- ESTACIONAMIENTOS
-- =====================================================

CREATE TABLE parkings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id BIGINT UNSIGNED NULL,

    slug VARCHAR(150) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NULL,

    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,

    capacity INT UNSIGNED NOT NULL DEFAULT 40,

    status ENUM('active', 'inactive')
        NOT NULL DEFAULT 'active',

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_parkings_owner
        FOREIGN KEY (owner_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- =====================================================
-- ESPACIOS
-- =====================================================

CREATE TABLE spaces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parking_id BIGINT UNSIGNED NOT NULL,

    code VARCHAR(30) NOT NULL,
    floor INT NOT NULL DEFAULT 1,

    type ENUM(
        'car',
        'moto',
        'ev',
        'disabled',
        'valet'
    ) NOT NULL DEFAULT 'car',

    status ENUM(
        'available',
        'reserved',
        'occupied',
        'maintenance'
    ) NOT NULL DEFAULT 'available',

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_space_parking_code (
        parking_id,
        code
    ),

    CONSTRAINT fk_spaces_parking
        FOREIGN KEY (parking_id)
        REFERENCES parkings(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- VEHICULOS
-- =====================================================

CREATE TABLE vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,

    plate VARCHAR(20) NOT NULL UNIQUE,
    brand VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    color VARCHAR(80) NULL,

    type ENUM(
        'car',
        'moto'
    ) NOT NULL DEFAULT 'car',

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_vehicles_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- RESERVAS MENSUALES
-- =====================================================

CREATE TABLE reservations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT UNSIGNED NOT NULL,
    parking_id BIGINT UNSIGNED NOT NULL,
    space_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NOT NULL,

    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,

    check_in_at DATETIME NULL,
    check_out_at DATETIME NULL,

    status ENUM(
        'confirmed',
        'active',
        'cancelled',
        'expired'
    ) NOT NULL DEFAULT 'confirmed',

    amount DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    currency CHAR(3) NOT NULL DEFAULT 'PEN',

    payment_status ENUM(
        'unpaid',
        'pending',
        'paid'
    ) NOT NULL DEFAULT 'unpaid',

    cancellation_status ENUM(
        'none',
        'pending',
        'approved',
        'rejected'
    ) NOT NULL DEFAULT 'none',

    cancellation_reason TEXT NULL,
    cancellation_requested_at DATETIME NULL,
    cancellation_reviewed_at DATETIME NULL,
    cancellation_reviewed_by BIGINT UNSIGNED NULL,
    cancellation_review_note TEXT NULL,

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_reservation_user
        FOREIGN KEY (user_id)
        REFERENCES users(id),

    CONSTRAINT fk_reservation_parking
        FOREIGN KEY (parking_id)
        REFERENCES parkings(id),

    CONSTRAINT fk_reservation_space
        FOREIGN KEY (space_id)
        REFERENCES spaces(id),

    CONSTRAINT fk_reservation_vehicle
        FOREIGN KEY (vehicle_id)
        REFERENCES vehicles(id),

    CONSTRAINT fk_reservation_reviewer
        FOREIGN KEY (cancellation_reviewed_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- =====================================================
-- PAGOS
-- =====================================================

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED NOT NULL,

    amount DECIMAL(10,2) NOT NULL,

    method VARCHAR(50) NOT NULL DEFAULT 'yape',
    provider VARCHAR(100) NULL,
    provider_tx_id VARCHAR(150) NULL,

    voucher_path VARCHAR(255) NULL,

    status ENUM(
        'pending',
        'paid',
        'rejected'
    ) NOT NULL DEFAULT 'pending',

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_payments_reservation
        FOREIGN KEY (reservation_id)
        REFERENCES reservations(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- CAMARAS
-- =====================================================

CREATE TABLE cameras (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parking_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(150) NOT NULL,
    stream_url TEXT NULL,
    coverage VARCHAR(255) NULL,

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_cameras_parking
        FOREIGN KEY (parking_id)
        REFERENCES parkings(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- AUDITORIA
-- =====================================================

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT UNSIGNED NULL,

    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,

    description TEXT NULL,
    metadata LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;