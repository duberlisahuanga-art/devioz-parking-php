-- Reejecutable si un DDL anterior fue aplicado pero no alcanzó a registrar historial.
ALTER TABLE payments ADD COLUMN IF NOT EXISTS active_reservation_id BIGINT UNSIGNED
 GENERATED ALWAYS AS (CASE WHEN status IN ('pending','paid') THEN reservation_id ELSE NULL END) PERSISTENT;
CREATE UNIQUE INDEX IF NOT EXISTS uq_payments_active_reservation ON payments(active_reservation_id);
