<?php

declare(strict_types=1);

final class VehicleCatalogImporter
{
    public static function import(PDO $pdo, string $directory): array
    {
        $makes = self::read($directory . '/makes.csv', ['make_slug', 'make_id', 'make_name']);
        $models = self::read(
            $directory . '/models.csv',
            ['make_slug', 'model_slug', 'model_name', 'model_id', 'vehicle_type']
        );
        $counts = ['makes_inserted' => 0, 'models_inserted' => 0, 'models_skipped' => 0];
        $pdo->beginTransaction();
        try {
            $make = $pdo->prepare(
                'INSERT INTO vehicle_catalog_makes(source_slug, source_id, name) VALUES(?, ?, ?)
                 ON DUPLICATE KEY UPDATE source_id = VALUES(source_id), name = VALUES(name)'
            );
            foreach ($makes as $row) {
                self::validate($row, 'make_slug', 'make_name', 'make_id');
                $make->execute([$row['make_slug'], $row['make_id'], $row['make_name']]);
                $counts['makes_inserted'] += $make->rowCount() === 1 ? 1 : 0;
            }
            $ids = $pdo->query('SELECT source_slug, id FROM vehicle_catalog_makes')->fetchAll(PDO::FETCH_KEY_PAIR);
            $model = $pdo->prepare(
                'INSERT INTO vehicle_catalog_models(make_id, source_slug, source_id, name, source_vehicle_type)
                 VALUES(?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE source_id = VALUES(source_id),
                 name = VALUES(name), source_vehicle_type = VALUES(source_vehicle_type)'
            );
            foreach ($models as $row) {
                self::validate($row, 'model_slug', 'model_name', 'model_id');
                if (!isset($ids[$row['make_slug']])) {
                    throw new RuntimeException('Modelo con marca desconocida en CSV.');
                }
                if (!in_array($row['vehicle_type'], ['car', 'mpv', 'truck'], true)) {
                    $counts['models_skipped']++;
                    continue;
                }
                $model->execute([$ids[$row['make_slug']], $row['model_slug'], $row['model_id'],
                    $row['model_name'], $row['vehicle_type']]);
                $counts['models_inserted'] += $model->rowCount() === 1 ? 1 : 0;
            }
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return $counts;
    }

    private static function read(string $path, array $required): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se puede leer el CSV: ' . basename($path));
        }
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (!is_array($header) || array_diff($required, $header) !== []) {
                throw new RuntimeException('Columnas CSV incompatibles: ' . basename($path));
            }
            $rows = [];
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header)) {
                    throw new RuntimeException('Fila CSV incompleta: ' . basename($path));
                }
                $rows[] = array_combine($header, $values);
            }
            if ($rows === []) {
                throw new RuntimeException('CSV vacío: ' . basename($path));
            }
            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private static function validate(array $row, string $slug, string $name, string $id): void
    {
        if (
            !preg_match('/^[a-z0-9_]{1,100}$/D', $row[$slug])
            || !mb_check_encoding($row[$name], 'UTF-8') || trim($row[$name]) === ''
            || mb_strlen($row[$name]) > 100 || !ctype_digit($row[$id]) || (int) $row[$id] < 1
        ) {
            throw new RuntimeException('Identificador o nombre inválido en CSV.');
        }
    }
}
