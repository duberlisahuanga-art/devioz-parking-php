<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$directory = $argv[1] ?? '';
if (preg_match('~^[A-Za-z]:[\\\\/]~', $directory) !== 1 || str_contains($directory, "\0")) {
    throw new RuntimeException('Indica una ruta absoluta local autorizada.');
}
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    throw new RuntimeException('No se pudo crear destino.');
}
$resolved = realpath($directory);
$root = realpath(dirname(__DIR__));
if ($resolved === false || $root === false || str_starts_with(strtolower($resolved . DIRECTORY_SEPARATOR), strtolower($root . DIRECTORY_SEPARATOR))) {
    throw new RuntimeException('La segunda copia debe quedar fuera del proyecto servido.');
}
$target = __DIR__ . '/../backend/config/local.php';
$values = is_file($target) ? require $target : [];
$values['AUDIT_BACKUP_MIRROR_DIR'] = $resolved;
$values['AUDIT_BACKUP_KEY'] ??= bin2hex(random_bytes(32));
$temporary = tempnam(dirname($target), 'audit-backup-');
if ($temporary === false || file_put_contents($temporary, "<?php\nreturn " . var_export($values, true) . ";\n", LOCK_EX) === false
    || !rename($temporary, $target)) {
    throw new RuntimeException('No se pudo guardar configuración de respaldo.');
}
echo "Destino de segunda copia configurado. No se imprimen claves.\n";
