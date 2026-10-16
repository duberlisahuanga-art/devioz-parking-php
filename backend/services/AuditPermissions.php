<?php

declare(strict_types=1);

final class AuditPermissions
{
    public static function grant(PDO $admin, string $database, string $account): void
    {
        if (preg_match('/^[a-zA-Z0-9_]+$/D', $database) !== 1) {
            throw new RuntimeException('Base de datos inválida.');
        }
        $query = $admin->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE=\'BASE TABLE\'');
        $query->execute([$database]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if ($table === 'schema_migrations') {
                continue;
            }
            if (preg_match('/^[a-zA-Z0-9_]+$/D', $table) !== 1) {
                throw new RuntimeException('Tabla inválida.');
            }
            $rights = $table === 'audit_logs' ? 'SELECT, INSERT' : 'SELECT, INSERT, UPDATE, DELETE';
            $admin->exec('GRANT ' . $rights . ' ON `' . $database . '`.`' . $table . '` TO ' . $account);
        }
    }

    public static function verify(PDO $connection): void
    {
        $connection->query('SELECT id FROM audit_logs LIMIT 1');
        $connection->query('SELECT id FROM users LIMIT 1');
        foreach (['UPDATE audit_logs SET id=id WHERE 1=0', 'DELETE FROM audit_logs WHERE 1=0'] as $sql) {
            try {
                $connection->exec($sql);
            } catch (PDOException $error) {
                if ((int) ($error->errorInfo[1] ?? 0) === 1142) {
                    continue;
                }
                throw $error;
            }
            throw new RuntimeException('La cuenta todavía puede alterar auditoría.');
        }
        $connection->beginTransaction();
        try {
            $connection->exec("INSERT INTO audit_logs(action,description) VALUES ('Verificacion','Prueba transaccional de permisos')");
        } finally {
            $connection->rollBack();
        }
    }
}
