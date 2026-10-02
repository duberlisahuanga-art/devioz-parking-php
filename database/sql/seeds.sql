USE devioz_parking;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM audit_logs;
DELETE FROM payments;
DELETE FROM reservations;
DELETE FROM vehicles;
DELETE FROM cameras;
DELETE FROM spaces;
DELETE FROM parkings;
DELETE FROM users;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- USUARIOS DEMO
-- Contraseña para ambos: password
-- =========================================================

INSERT INTO users (
    name,
    email,
    password,
    phone,
    role,
    status,
    terms_accepted_at,
    created_at,
    updated_at
) VALUES
(
    'Administrador Devioz',
    'admin@devioz.test',
    '$2y$12$z9/lJ1Nh0Bn3EhgQlzdblepxB3aqjhAh53RrZAWLp87KgrKE5jdqq',
    NULL,
    'admin',
    'active',
    NOW(),
    NOW(),
    NOW()
),
(
    'Conductor Devioz',
    'driver@devioz.test',
    '$2y$12$z9/lJ1Nh0Bn3EhgQlzdblepxB3aqjhAh53RrZAWLp87KgrKE5jdqq',
    '+51 999 999 999',
    'driver',
    'active',
    NOW(),
    NOW(),
    NOW()
);

-- =========================================================
-- ESTACIONAMIENTO
-- =========================================================

INSERT INTO parkings (
    owner_id,
    slug,
    name,
    address,
    lat,
    lng,
    capacity,
    status,
    created_at,
    updated_at
)
SELECT
    id,
    'estacionamiento-devioz',
    'Estacionamiento Devioz',
    'Dirección pendiente de confirmar',
    -12.0984,
    -77.0365,
    40,
    'active',
    NOW(),
    NOW()
FROM users
WHERE email = 'admin@devioz.test';

-- =========================================================
-- 40 PLAZAS
-- =========================================================

INSERT INTO spaces (
    parking_id,
    code,
    floor,
    type,
    status,
    created_at,
    updated_at
)
SELECT id, 'P-01', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-02', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-03', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-04', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-05', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-06', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-07', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-08', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-09', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-10', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-11', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-12', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-13', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-14', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-15', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-16', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-17', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-18', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-19', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-20', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-21', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-22', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-23', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-24', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-25', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-26', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-27', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-28', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-29', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-30', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-31', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-32', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-33', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-34', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-35', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-36', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-37', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-38', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-39', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

INSERT INTO spaces (parking_id, code, floor, type, status, created_at, updated_at)
SELECT id, 'P-40', 1, 'car', 'available', NOW(), NOW()
FROM parkings WHERE slug = 'estacionamiento-devioz';

-- =========================================================
-- VEHÍCULO DEMO
-- =========================================================

INSERT INTO vehicles (
    user_id,
    plate,
    brand,
    model,
    color,
    type,
    created_at,
    updated_at
)
SELECT
    id,
    'ABC-123',
    'Toyota',
    'Corolla',
    'Blanco',
    'car',
    NOW(),
    NOW()
FROM users
WHERE email = 'driver@devioz.test';

-- =========================================================
-- CÁMARAS
-- =========================================================

INSERT INTO cameras (
    parking_id,
    name,
    stream_url,
    coverage,
    status,
    created_at,
    updated_at
)
SELECT
    id,
    'Cámara Entrada',
    'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
    'Entrada principal',
    'active',
    NOW(),
    NOW()
FROM parkings
WHERE slug = 'estacionamiento-devioz';

INSERT INTO cameras (
    parking_id,
    name,
    stream_url,
    coverage,
    status,
    created_at,
    updated_at
)
SELECT
    id,
    'Cámara Zona A',
    NULL,
    'Plazas P-01 a P-20',
    'active',
    NOW(),
    NOW()
FROM parkings
WHERE slug = 'estacionamiento-devioz';

INSERT INTO cameras (
    parking_id,
    name,
    stream_url,
    coverage,
    status,
    created_at,
    updated_at
)
SELECT
    id,
    'Cámara Zona B',
    NULL,
    'Plazas P-21 a P-40',
    'active',
    NOW(),
    NOW()
FROM parkings
WHERE slug = 'estacionamiento-devioz';