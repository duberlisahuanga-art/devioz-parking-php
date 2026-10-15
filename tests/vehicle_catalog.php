<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}
session_start();
$root = dirname(__DIR__);
$database = 'devioz_catalog_test_' . bin2hex(random_bytes(6));
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$username = getenv('TEST_DB_USERNAME') ?: 'root';
$password = getenv('TEST_DB_PASSWORD') ?: '';
$pdo = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$temp = sys_get_temp_dir() . '/devioz-catalog-' . bin2hex(random_bytes(6));
$passed = 0;
function catalogCheck(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FALLO: ' . $name);
    }
    $passed++;
    echo 'OK: ' . $name . PHP_EOL;
}
try {
    $pdo->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/sql/schema.sql')));
    $migration = file_get_contents($root . '/database/migrations/20261015_vehicle_catalog.sql');
    $pdo->exec($migration);
    $pdo->exec($migration);
    require $root . '/backend/services/VehicleCatalogImporter.php';
    $directory = $root . '/database/import/open-vehicle-db';
    $first = VehicleCatalogImporter::import($pdo, $directory);
    catalogCheck($first['makes_inserted'] === 70 && $first['models_inserted'] === 1678, 'CSV real: 70 marcas, 1678 modelos');
    $second = VehicleCatalogImporter::import($pdo, $directory);
    catalogCheck($second['makes_inserted'] === 0 && $second['models_inserted'] === 0, 'segunda importación sin duplicados');
    catalogCheck($first['models_skipped'] === 0, 'solo tipos de cuatro ruedas en el CSV actual');
    putenv('DB_HOST=' . $host);
    putenv('DB_DATABASE=' . $database);
    putenv('DB_USERNAME=' . $username);
    putenv('DB_PASSWORD=' . $password);
    require $root . '/backend/bootstrap.php';
    restore_exception_handler();
    require_once $root . '/backend/models/VehicleCatalog.php';
    $toyota = VehicleCatalog::searchMakes('tOy');
    catalogCheck(count($toyota) === 1 && $toyota[0]['name'] === 'TOYOTA', 'Toy encuentra Toyota sin distinguir mayúsculas');
    $toyotaId = (int) $toyota[0]['id'];
    $models = VehicleCatalog::searchModels($toyotaId, 'Cor');
    catalogCheck(in_array('Corolla', array_column($models, 'name'), true), 'Toyota + Cor incluye Corolla');
    catalogCheck(in_array('Corolla Cross', array_column($models, 'name'), true), 'Toyota + Cor incluye Corolla Cross');
    $honda = VehicleCatalog::searchMakes('Honda')[0];
    catalogCheck(VehicleCatalog::searchModels((int) $honda['id'], 'Corolla') === [], 'modelos aislados por marca');
    catalogCheck(VehicleCatalog::searchMakes('', 'moto') === [], 'moto no devuelve marcas del catálogo');
    catalogCheck(VehicleCatalog::searchMakes('%') === [] && VehicleCatalog::searchMakes('_') === [], 'comodines tratados como texto');
    catalogCheck(VehicleCatalog::searchMakes("' OR 1=1 --") === [], 'consulta con comillas no altera SQL');
    catalogCheck(count(VehicleCatalog::searchMakes('', 'car', 999)) <= 20, 'límite máximo de resultados');
    catalogCheck(VehicleCatalog::searchModels(999999, '') === [], 'marca inexistente sin resultados');
    db()->exec('UPDATE vehicle_catalog_makes SET is_active=0 WHERE id=' . $toyotaId);
    catalogCheck(VehicleCatalog::searchModels($toyotaId, '') === [], 'marca inactiva no ofrece modelos');
    db()->exec('UPDATE vehicle_catalog_makes SET is_active=1 WHERE id=' . $toyotaId);
    mkdir($temp);
    file_put_contents($temp . '/makes.csv', "make_slug,make_id,make_name\nrollback_test,999999,Rollback Test\n");
    file_put_contents($temp . '/models.csv', "make_slug,model_slug,model_name,model_id,vehicle_type\nmissing,test,Test,999999,car\n");
    try {
        VehicleCatalogImporter::import($pdo, $temp);
        throw new LogicException('La importación inválida debió fallar.');
    } catch (RuntimeException $error) {
        catalogCheck((int) $pdo->query("SELECT COUNT(*) FROM vehicle_catalog_makes WHERE source_slug='rollback_test'")
            ->fetchColumn() === 0, 'fallo de modelo revierte también la marca importada');
    }
    User::create(['name' => 'Catálogo Test', 'email' => 'catalog@test.invalid',
        'password' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => 'driver', 'status' => 'active']);
    $_SESSION['user_id'] = (int) db()->lastInsertId();
    foreach (['car' => 'CAT-123', 'moto' => 'MOT-123'] as $type => $plate) {
        $id = VehicleController::store(['plate' => $plate, 'brand' => 'Marca fuera de catálogo',
            'model' => 'Modelo propio', 'color' => 'Blanco', 'type' => $type, 'catalog_manual' => '1']);
        $saved = Vehicle::find($id);
        catalogCheck($saved['brand'] === 'Marca fuera de catálogo' && $saved['model'] === 'Modelo propio'
            && $saved['type'] === $type, 'registro manual conservado: ' . $type);
    }
    catalogCheck(array_slice(array_column(VehicleCatalog::searchMakes(''), 'name'), 0, 3)
        === ['TOYOTA', 'KIA', 'HYUNDAI'], 'marcas frecuentes primero sin borrar las demás');
    catalogCheck(VehicleCatalog::searchMakes('Acu')[0]['name'] === 'ACURA', 'otras marcas siguen disponibles');
    $valid = VehicleCatalog::validateSelection('toyota', 'corolla', false);
    catalogCheck($valid === ['brand' => 'TOYOTA', 'model' => 'Corolla'], 'pareja válida se canoniza en servidor');
    $manual = VehicleCatalog::validateSelection('Toyota', 'Modelo fuera del listado', true);
    catalogCheck($manual['model'] === 'Modelo fuera del listado', 'modelo ausente permitido con confirmación explícita');
    foreach ([['Toyota', 'Kia', false], ['Toyota', 'Kia', true], ['Toyota', 'Sportage', true],
        ['Toyota', 'Modelo fuera del listado', false], ['Marca propia', 'Modelo propio', false]] as $case) {
        try {
            VehicleCatalog::validateSelection(...$case);
            throw new LogicException('La combinación debió rechazarse.');
        } catch (ValidationException $error) {
            catalogCheck(true, 'combinación rechazada: ' . $case[0] . '/' . $case[1] . ' manual=' . (int) $case[2]);
        }
    }
    $before = (int) db()->query('SELECT COUNT(*) FROM vehicles')->fetchColumn();
    try {
        VehicleController::store(['plate' => 'BAD-123', 'brand' => 'Toyota', 'model' => 'Kia',
            'type' => 'car', 'catalog_manual' => '1']);
        throw new LogicException('El controlador debió rechazar Toyota/Kia.');
    } catch (ValidationException $error) {
        catalogCheck((int) db()->query('SELECT COUNT(*) FROM vehicles')->fetchColumn() === $before,
            'POST inválido no crea vehículo aun omitiendo JavaScript');
    }
    foreach (['F3V-802', 'ABC1234', 'abc123', '12AB3', '1234567'] as $plate) {
        catalogCheck(validate_peruvian_plate($plate), 'placa alfanumérica admitida: ' . $plate);
    }
    foreach (['', 'ABC12345', 'ABCD-123', 'AB 123', 'AB_123', 'AB--12', '-AB123', 'AB123-', 'ÁBC123'] as $plate) {
        catalogCheck(!validate_peruvian_plate($plate), 'placa inválida rechazada: ' . $plate);
    }
    $id = VehicleController::store(['plate' => 'f3v-802', 'brand' => 'Toyota', 'model' => 'Corolla', 'type' => 'car']);
    catalogCheck(Vehicle::find($id)['plate'] === 'F3V-802', 'registro real acepta F3V-802 y normaliza mayúsculas');
    try {
        VehicleController::store(['plate' => 'f3v802', 'brand' => 'Toyota', 'model' => 'Corolla', 'type' => 'car']);
        throw new LogicException('La placa equivalente debió detectarse.');
    } catch (ValidationException $error) {
        catalogCheck(str_contains($error->getMessage(), 'registrada'), 'duplicado con/sin guion rechazado');
    }
    echo $passed . ' comprobaciones correctas.' . PHP_EOL;
} finally {
    // Only the generated test database and our two synthetic CSV files are disposable.
    $pdo->exec('DROP DATABASE `' . $database . '`');
    foreach (['makes.csv', 'models.csv'] as $file) {
        if (is_file($temp . '/' . $file)) {
            unlink($temp . '/' . $file);
        }
    }
    if (is_dir($temp)) {
        rmdir($temp);
    }
}
