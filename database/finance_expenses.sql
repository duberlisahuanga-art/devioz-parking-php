-- Ejecutar en la base devioz_parking. No modifica payments.
USE devioz_parking;
CREATE TABLE IF NOT EXISTS expenses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category ENUM('Mantenimiento','Limpieza','Personal','Otros') NOT NULL,
 concept VARCHAR(255) NOT NULL,
 amount DECIMAL(10,2) NOT NULL,
 expense_datetime DATETIME NOT NULL,
 payment_method ENUM('Yape','Efectivo','Transferencia bancaria') NOT NULL,
 description TEXT NOT NULL,
 other_detail VARCHAR(1000) NULL,
 voucher_path VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_expenses_datetime (expense_datetime),
 INDEX idx_expenses_category_datetime (category, expense_datetime),
 INDEX idx_expenses_creator (created_by),
 CONSTRAINT fk_expenses_creator FOREIGN KEY (created_by) REFERENCES users(id),
 CONSTRAINT chk_expenses_amount CHECK (amount > 0),
 CONSTRAINT chk_expenses_other CHECK (category <> 'Otros' OR (other_detail IS NOT NULL AND CHAR_LENGTH(TRIM(other_detail)) > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;