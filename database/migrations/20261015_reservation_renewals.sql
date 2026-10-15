-- Additive provenance; a previous monthly reservation can be renewed only once.
ALTER TABLE reservations
    ADD COLUMN renewed_from_id BIGINT UNSIGNED NULL,
    ADD UNIQUE KEY uq_reservation_renewal (renewed_from_id),
    ADD KEY idx_reservation_expiry (status, ends_on),
    ADD CONSTRAINT fk_reservation_renewal FOREIGN KEY (renewed_from_id)
        REFERENCES reservations(id);
