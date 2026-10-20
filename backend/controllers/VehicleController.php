<?php

declare(strict_types=1);

final class VehicleController
{
    public static function index(): array
    {
        $user = DriverContext::managed() ?? require_auth();
        return Vehicle::forUser((int) $user['id']);
    }

    public static function store(array $data): int
    {
        $user = DriverContext::user();

        $rawPlate = is_string($data['plate'] ?? null) ? trim($data['plate']) : '';
        if (!validate_peruvian_plate($rawPlate)) {
            throw new ValidationException(
                'Placa inválida: usa letras y números, con un guion opcional y máximo 7 caracteres.'
            );
        }

        $plate = normalize_plate($rawPlate);
        if (Vehicle::plateExists($plate)) {
            throw new ValidationException('La placa ya se encuentra registrada.');
        }

        $type = (string) ($data['type'] ?? 'car');
        if (!in_array($type, ['car', 'moto'], true)) {
            throw new ValidationException('Tipo de vehiculo invalido.');
        }

        $brand = clean_string($data['brand'] ?? '', 100);
        $model = clean_string($data['model'] ?? '', 100);
        if ($brand === '' || $model === '') {
            throw new ValidationException('Completa la marca y el modelo del vehículo.');
        }
        $selection = ['brand' => $brand, 'model' => $model];
        if ($type === 'car') {
            $selection = VehicleCatalog::validateSelection($brand, $model, ($data['catalog_manual'] ?? '') === '1');
        }

        return transaction(function () use ($user, $plate, $selection, $data, $type): int {
            $id = Vehicle::create((int) $user['id'], [
                'plate' => $plate,
                'brand' => $selection['brand'],
                'model' => $selection['model'],
                'color' => clean_string($data['color'] ?? '', 50),
                'type' => $type,
            ]);

            AuditService::log('Vehiculos', 'Creado', "Vehiculo {$plate} registrado.", 'Vehicle', $id, $plate);
            return $id;
        });
    }

    public static function destroy(int $vehicleId): void
    {
        $user = DriverContext::user();
        $vehicle = Vehicle::findOwned($vehicleId, (int) $user['id']);
        if (!$vehicle) {
            AuditService::denied('vehicle_owner_required');
            throw new ValidationException('Vehiculo no encontrado.');
        }
        if (Vehicle::hasOpenReservation($vehicleId)) {
            throw new ValidationException('No puedes eliminar un vehiculo con una reserva vigente.');
        }

        transaction(function () use ($vehicleId, $vehicle): void {
            Vehicle::delete($vehicleId);
            AuditService::log('Vehiculos', 'Eliminado', "Vehiculo {$vehicle['plate']} eliminado.", 'Vehicle', $vehicleId, $vehicle['plate']);
        });
    }
}
