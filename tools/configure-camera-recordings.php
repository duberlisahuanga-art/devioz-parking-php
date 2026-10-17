<?php

declare(strict_types=1);

// Instalador local acotado a la migración de grabaciones. No arranca ni repara MariaDB.
if (PHP_SAPI !== 'cli') {
    exit;
}
require_once __DIR__ . '/../backend/config/app.php';

try {
    if (AppConfig::production() || !in_array(AppConfig::get('DB_HOST', '127.0.0.1'), ['127.0.0.1', 'localhost'], true)) {
        throw new RuntimeException('Este instalador sólo admite el entorno local.');
    }
    $apply = ($argv[1] ?? '') === '--apply';
    $database = AppConfig::get('DB_DATABASE', 'devioz_parking');
    $runtimeUser = AppConfig::get('DB_USERNAME');
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $database) || !preg_match('/^devioz_app_[a-f0-9]+$/D', $runtimeUser)) {
        throw new RuntimeException('Se necesita una base identificable y la cuenta local limitada de DEVIOZ.');
    }
    $username = getenv('DB_MIGRATION_USERNAME') ?: 'root';
    $password = getenv('DB_MIGRATION_PASSWORD') ?: '';
    $dsn = 'mysql:host=' . AppConfig::get('DB_HOST', '127.0.0.1') . ';port=' . AppConfig::get('DB_PORT', '3306')
        . ';dbname=' . $database . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ((int) $pdo->query("SELECT GET_LOCK('devioz_migrations', 10)")->fetchColumn() !== 1) {
        throw new RuntimeException('Otra migración está en curso. Vuelve a intentar.');
    }
    try {
        $version = '20261016_camera_recordings.sql';
        $file = __DIR__ . '/../database/migrations/' . $version;
        $hash = hash_file('sha256', $file);
        $check = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version=?');
        $check->execute([$version]);
        $previous = $check->fetchColumn();
        if ($previous !== false && $previous !== $hash) {
            throw new RuntimeException('La migración instalada tiene otro checksum. Revisión necesaria.');
        }
        $hasTable = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()"
            . " AND table_name='camera_recordings'")->fetchColumn() === 1;
        if ($hasTable !== ($previous !== false)) {
            throw new RuntimeException('Tabla y registro de migración no coinciden. Revisar la instalación antes de continuar.');
        }
        if (!$apply) {
            echo $hasTable ? "La migración ya está instalada.\n" : "Pendiente: crear la tabla de grabaciones; ejecutar con --apply.\n";
            exit;
        }
        if (!$hasTable) {
            $directory = AppConfig::storage() . '/camera-recording-migration';
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('No se pudo preparar el directorio de respaldo.');
            }
            $backup = $directory . '/before-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sql';
            $dump = AppConfig::get('MYSQLDUMP_BIN', 'C:/xampp/mysql/bin/mysqldump.exe');
            $process = proc_open(
                [$dump, '--host=' . AppConfig::get('DB_HOST', '127.0.0.1'), '--port=' . AppConfig::get('DB_PORT', '3306'),
                    '--user=' . $username, '--single-transaction', '--result-file=' . $backup, $database],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__),
                array_merge(getenv(), ['MYSQL_PWD' => $password])
            );
            if (!is_resource($process)) {
                throw new RuntimeException('No se pudo iniciar el respaldo. No se aplicó la migración.');
            }
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0 || !is_file($backup) || filesize($backup) === 0) {
                throw new RuntimeException('El respaldo falló. No se aplicó la migración.');
            }
            @chmod($backup, 0600);
            $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
            $restore = 'devioz_recording_check_' . bin2hex(random_bytes(6));
            $pdo->exec('CREATE DATABASE `' . $restore . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            try {
                $pdo->exec('USE `' . $restore . '`');
                $pdo->exec(file_get_contents($backup));
                foreach ($tables as $table) {
                    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
                        throw new RuntimeException('Nombre de tabla inesperado en la verificación.');
                    }
                    $copy = $pdo->query('SELECT COUNT(*) FROM `' . $restore . '`.`' . $table . '`')->fetchColumn();
                    $live = $pdo->query('SELECT COUNT(*) FROM `' . $database . '`.`' . $table . '`')->fetchColumn();
                    if ($copy !== $live) {
                        throw new RuntimeException('Los conteos del respaldo no coinciden. No se aplicó la migración.');
                    }
                }
            } finally {
                $pdo->exec('USE `' . $database . '`');
                $pdo->exec('DROP DATABASE `' . $restore . '`');
            }
            echo "Respaldo completo creado y restauración verificada para todas las tablas.\n";
            // DDL confirma implícitamente. El respaldo permanece privado para recuperación.
            $pdo->exec(file_get_contents($file));
            $pdo->prepare('INSERT INTO schema_migrations(version,checksum) VALUES (?,?)')->execute([$version, $hash]);
            echo "Migración aditiva de grabaciones aplicada.\n";
        }
        $account = $pdo->quote($runtimeUser) . "@'127.0.0.1'";
        $pdo->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `' . $database . '`.camera_recordings TO ' . $account);
        $runtime = new PDO($dsn, $runtimeUser, AppConfig::get('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $runtime->query('SELECT id FROM camera_recordings LIMIT 1');
        echo "Acceso a la tabla verificado con la cuenta limitada. Configuración SMTP intacta.\n";
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('devioz_migrations')");
    }
} catch (Throwable $error) {
    // Evitar imprimir DSN, contraseñas, GRANT o datos en errores de herramientas.
    fwrite(STDERR, 'No se completó la instalación: ' . ($error instanceof PDOException ? 'falló una operación de base de datos.' : $error->getMessage()) . "\n");
    exit(1);
}
