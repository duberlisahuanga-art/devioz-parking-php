<?php

declare(strict_types=1);

final class VehicleCatalog
{
    // UI preference for Peru; only orders makes actually present in the local catalog.
    private const PREFERRED_MAKES = [
        'toyota', 'kia', 'hyundai', 'chevrolet', 'suzuki', 'nissan', 'mitsubishi',
        'mazda', 'honda', 'ford', 'renault', 'peugeot',
    ];

    public static function searchMakes(string $query, string $type = 'car', int $limit = 15): array
    {
        if ($type !== 'car') {
            return [];
        }
        $priorities = [];
        foreach (self::PREFERRED_MAKES as $rank => $slug) {
            $priorities[] = 'WHEN :preferred_' . $rank . ' THEN ' . $rank;
        }
        $stmt = db()->prepare(
            'SELECT id, name FROM vehicle_catalog_makes
             WHERE is_active = 1 AND name LIKE :query ESCAPE \'!\'
             ORDER BY CASE source_slug ' . implode(' ', $priorities) . ' ELSE 100 END, name, id LIMIT :limit'
        );
        foreach (self::PREFERRED_MAKES as $rank => $slug) {
            $stmt->bindValue('preferred_' . $rank, $slug);
        }
        $stmt->bindValue('query', self::prefix($query));
        $stmt->bindValue('limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function searchModels(int $makeId, string $query, int $limit = 15): array
    {
        $stmt = db()->prepare(
            'SELECT m.id, m.name FROM vehicle_catalog_models m
             INNER JOIN vehicle_catalog_makes b ON b.id = m.make_id AND b.is_active = 1
             WHERE m.make_id = :make_id AND m.is_active = 1
               AND m.name LIKE :query ESCAPE \'!\'
             ORDER BY m.name, m.id LIMIT :limit'
        );
        $stmt->bindValue('make_id', $makeId, PDO::PARAM_INT);
        $stmt->bindValue('query', self::prefix($query));
        $stmt->bindValue('limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function prefix(string $query): string
    {
        return strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }

    public static function validateSelection(string $brand, string $model, bool $manual): array
    {
        $make = db()->prepare('SELECT id, name FROM vehicle_catalog_makes WHERE name = :name AND is_active = 1');
        $make->execute(['name' => $brand]);
        $selectedMake = $make->fetch();
        if ($selectedMake) {
            $pair = db()->prepare(
                'SELECT name FROM vehicle_catalog_models
                 WHERE make_id = :make_id AND name = :name AND is_active = 1 LIMIT 1'
            );
            $pair->execute(['make_id' => $selectedMake['id'], 'name' => $model]);
            $knownModel = $pair->fetchColumn();
            if ($knownModel !== false) {
                return ['brand' => $selectedMake['name'], 'model' => $knownModel];
            }
        }

        // Manual entry cannot bypass a known brand/model contradiction.
        $make->execute(['name' => $model]);
        if ($make->fetch()) {
            throw new ValidationException('El modelo indicado es una marca. Selecciona el modelo de tu vehículo.');
        }
        if ($selectedMake) {
            $other = db()->prepare(
                'SELECT 1 FROM vehicle_catalog_models m
                 INNER JOIN vehicle_catalog_makes b ON b.id = m.make_id AND b.is_active = 1
                 WHERE m.name = :name AND m.is_active = 1 LIMIT 1'
            );
            $other->execute(['name' => $model]);
            if ($other->fetchColumn()) {
                throw new ValidationException('El modelo no corresponde a la marca indicada. Revisa marca y modelo.');
            }
        }
        if (!$manual) {
            throw new ValidationException(
                'Selecciona una marca y su modelo del catálogo o marca «Mi vehículo no aparece en el catálogo».'
            );
        }
        return ['brand' => $selectedMake['name'] ?? $brand, 'model' => $model];
    }
}
