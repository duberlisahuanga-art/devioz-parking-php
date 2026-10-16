<?php

declare(strict_types=1);

final class AuditArchive
{
    public static function backup(PDO $connection, string $path, string $key): int
    {
        if (strlen($key) < 32) {
            throw new RuntimeException('El respaldo requiere clave y conexión sin transacción activa.');
        }
        $file = fopen($path, 'x+b');
        if ($file === false) {
            throw new RuntimeException('No se pudo crear respaldo exclusivo.');
        }
        $count = 0;
        $connection->beginTransaction();
        try {
            $rows = $connection->query('SELECT * FROM audit_logs ORDER BY id');
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $line = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";
                if (fwrite($file, $line) !== strlen($line)) {
                    throw new RuntimeException('Escritura incompleta del respaldo.');
                }
                $count++;
            }
            fflush($file);
            $connection->commit();
        } catch (Throwable $error) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $error;
        } finally {
            fclose($file);
        }
        $manifest = ['format' => 1, 'rows' => $count, 'created_at' => date(DATE_ATOM),
            'hmac_sha256' => hash_hmac_file('sha256', $path, $key)];
        $manifestFile = fopen($path . '.manifest.json', 'x+b');
        if ($manifestFile === false) {
            throw new RuntimeException('No se pudo crear manifiesto.');
        }
        try {
            $json = json_encode($manifest, JSON_THROW_ON_ERROR);
            if (fwrite($manifestFile, $json) !== strlen($json)) {
                throw new RuntimeException('Manifiesto incompleto.');
            }
        } finally {
            fclose($manifestFile);
        }
        return $count;
    }

    public static function mirror(string $source, string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Destino de copia no disponible.');
        }
        $target = $directory . '/' . basename($source);
        $input = fopen($source, 'rb');
        $output = fopen($target, 'xb');
        if ($input === false || $output === false) {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
            throw new RuntimeException('No se pudo abrir copia exclusiva.');
        }
        try {
            if (stream_copy_to_stream($input, $output) !== filesize($source)) {
                throw new RuntimeException('Copia incompleta.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
        if (!hash_equals(hash_file('sha256', $source), hash_file('sha256', $target))) {
            throw new RuntimeException('La copia no coincide con el original.');
        }
    }

    // Restores only into a connection-local temporary table; operational data stays untouched.
    public static function verify(PDO $connection, string $path, string $key): int
    {
        if (strlen($key) < 32) {
            throw new RuntimeException('Clave de respaldo inválida.');
        }
        $manifest = json_decode((string) file_get_contents($path . '.manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 1 || !hash_equals((string) $manifest['hmac_sha256'], hash_hmac_file('sha256', $path, $key))) {
            throw new RuntimeException('Integridad del respaldo incorrecta.');
        }
        $connection->exec('CREATE TEMPORARY TABLE audit_restore_verify (id BIGINT UNSIGNED PRIMARY KEY, user_id BIGINT UNSIGNED NULL,
            action VARCHAR(100), entity_type VARCHAR(100), entity_id BIGINT UNSIGNED NULL, description LONGTEXT,
            metadata LONGTEXT, ip_address VARCHAR(45), created_at DATETIME NULL) ENGINE=InnoDB');
        $file = fopen($path, 'rb');
        if ($file === false) {
            $connection->exec('DROP TEMPORARY TABLE audit_restore_verify');
            throw new RuntimeException('Respaldo no legible.');
        }
        $count = 0;
        $expected = hash_init('sha256');
        try {
            $insert = $connection->prepare('INSERT INTO audit_restore_verify
                (id,user_id,action,entity_type,entity_id,description,metadata,ip_address,created_at)
                VALUES (:id,:user_id,:action,:entity_type,:entity_id,:description,:metadata,:ip_address,:created_at)');
            while (($line = fgets($file)) !== false) {
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $insert->execute($row);
                hash_update($expected, json_encode(array_values($row), JSON_THROW_ON_ERROR));
                $count++;
            }
            if (!feof($file)) {
                throw new RuntimeException('Lectura incompleta.');
            }
            $actual = hash_init('sha256');
            $restored = $connection->query('SELECT * FROM audit_restore_verify ORDER BY id');
            while ($row = $restored->fetch(PDO::FETCH_ASSOC)) {
                // PDO may return numeric columns as integers, matching the source connection.
                hash_update($actual, json_encode(array_values($row), JSON_THROW_ON_ERROR));
            }
            if ($count !== $manifest['rows'] || !hash_equals(hash_final($expected), hash_final($actual))) {
                throw new RuntimeException('La restauración no coincide con el respaldo.');
            }
        } finally {
            fclose($file);
            $connection->exec('DROP TEMPORARY TABLE audit_restore_verify');
        }
        return $count;
    }
}
