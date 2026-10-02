<?php

declare(strict_types=1);

final class CameraController
{
    public static function index(): array
    {
        require_admin();
        return Camera::all();
    }

    public static function store(array $data): int
    {
        require_admin();
        $parkingId = (int) ($data['parking_id'] ?? 0);
        if (!Parking::find($parkingId)) {
            throw new RuntimeException('Estacionamiento invalido.');
        }

        $name = clean_string($data['name'] ?? '', 120);
        if ($name === '') {
            throw new RuntimeException('El nombre de la camara es obligatorio.');
        }

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Estado de camara invalido.');
        }

        $id = Camera::create([
            'parking_id' => $parkingId,
            'name' => $name,
            'stream_url' => clean_string($data['stream_url'] ?? '', 500) ?: null,
            'coverage' => clean_string($data['coverage'] ?? '', 255) ?: null,
            'status' => $status,
        ]);

        AuditService::log('Camaras', 'Creada', "Camara {$name} creada.", 'Camera', $id, $name);
        return $id;
    }

    public static function update(int $cameraId, array $data): void
    {
        require_admin();
        $camera = Camera::find($cameraId);
        if (!$camera) {
            throw new RuntimeException('Camara no encontrada.');
        }

        $parkingId = (int) ($data['parking_id'] ?? $camera['parking_id']);
        if (!Parking::find($parkingId)) {
            throw new RuntimeException('Estacionamiento invalido.');
        }

        $status = (string) ($data['status'] ?? $camera['status']);
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Estado de camara invalido.');
        }

        Camera::update($cameraId, [
            'parking_id' => $parkingId,
            'name' => clean_string($data['name'] ?? $camera['name'], 120),
            'stream_url' => clean_string($data['stream_url'] ?? ($camera['stream_url'] ?? ''), 500) ?: null,
            'coverage' => clean_string($data['coverage'] ?? ($camera['coverage'] ?? ''), 255) ?: null,
            'status' => $status,
        ]);

        AuditService::log('Camaras', 'Actualizada', "Camara #{$cameraId} actualizada.", 'Camera', $cameraId, (string) $camera['name']);
    }

    public static function destroy(int $cameraId): void
    {
        require_admin();
        $camera = Camera::find($cameraId);
        if (!$camera) {
            throw new RuntimeException('Camara no encontrada.');
        }
        Camera::delete($cameraId);
        AuditService::log('Camaras', 'Eliminada', "Camara {$camera['name']} eliminada.", 'Camera', $cameraId, (string) $camera['name']);
    }
}
