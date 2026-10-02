<?php

declare(strict_types=1);

final class VehicleController
{
    public static function index(): array
    {
        $user = require_auth();
        return Vehicle::forUser((int) $user['id']);
    }

    public static function store(array $data): int
    {
        $user = require_driver();

        $rawPlate = clean_string($data['plate'] ?? '', 20);
        if (!validate_peruvian_plate($rawPlate)) {
            throw new RuntimeException('Formato de placa peruana invalido. Ejemplo: ABC-123.');
        }

        $plate = normalize_plate($rawPlate);
        if (Vehicle::plateExists($plate)) {
            throw new RuntimeException('La placa ya se encuentra registrada.');
        }

        $type = (string) ($data['type'] ?? 'car');
        if (!in_array($type, ['car', 'moto'], true)) {
            throw new RuntimeException('Tipo de vehiculo invalido.');
        }

        $id = Vehicle::create((int) $user['id'], [
            'plate' => $plate,
            'brand' => clean_string($data['brand'] ?? '', 100),
            'model' => clean_string($data['model'] ?? '', 100),
            'color' => clean_string($data['color'] ?? '', 50),
            'type' => $type,
        ]);

        AuditService::log('Vehiculos', 'Creado', "Vehiculo {$plate} registrado.", 'Vehicle', $id, $plate);
        return $id;
    }

    public static function destroy(int $vehicleId): void
    {
        $user = require_driver();
        $vehicle = Vehicle::findOwned($vehicleId, (int) $user['id']);
        if (!$vehicle) {
            throw new RuntimeException('Vehiculo no encontrado.');
        }
        if (Vehicle::hasOpenReservation($vehicleId)) {
            throw new RuntimeException('No puedes eliminar un vehiculo con una reserva vigente.');
        }

        Vehicle::delete($vehicleId);
        AuditService::log('Vehiculos', 'Eliminado', "Vehiculo {$vehicle['plate']} eliminado.", 'Vehicle', $vehicleId, $vehicle['plate']);
    }
}
