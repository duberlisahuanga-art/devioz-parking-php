<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../backend/config/conexion.php';
require_once __DIR__ . '/../backend/services/VehicleCatalogImporter.php';

try {
    $directory = __DIR__ . '/../database/import/open-vehicle-db';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--csv-dir=')) {
            $directory = substr($arg, 10);
        }
    }
    $counts = VehicleCatalogImporter::import($pdo, $directory);
    echo json_encode($counts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    foreach (['vehicle_catalog_makes', 'vehicle_catalog_models'] as $table) {
        echo $table . ': ' . $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Importación fallida; revisa CSV, migración y permisos de la base. Código: '
        . $error->getCode() . PHP_EOL);
    exit(1);
}
