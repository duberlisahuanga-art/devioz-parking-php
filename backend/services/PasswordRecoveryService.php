<?php

declare(strict_types=1);

final class PasswordRecoveryService
{
    public const MESSAGE = 'Si el correo corresponde a una cuenta activa, recibirás un enlace para recuperar tu contraseña.';

    private static function key(): string
    {
        $secret = AppConfig::get('APP_SECRET');
        if (strlen($secret) < 32) {
            throw new RuntimeException('Falta APP_SECRET.');
        }
        return hash('sha256', 'password-recovery:' . $secret, true);
    }

    public static function request(string $email): void
    {
        $email = strtolower(trim($email));
        RateLimiter::consume('recovery-ip:' . request_ip(), 10, 900);
        RateLimiter::consume('recovery-email:' . $email, 3, 900);
        if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($token, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) {
            throw new RuntimeException('No se pudo preparar recuperación.');
        }
        transaction(function () use ($email, $token, $nonce, $tag, $cipher): void {
            $find = db()->prepare('SELECT * FROM users WHERE email=? FOR UPDATE');
            $find->execute([$email]);
            $user = $find->fetch();
            if (!$user || $user['status'] !== 'active') {
                return;
            }
            $save = db()->prepare('INSERT INTO password_resets
                (user_id,token_hash,email,password_fingerprint,encrypted_token,expires_at)
                VALUES (?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),email=VALUES(email),
                password_fingerprint=VALUES(password_fingerprint),encrypted_token=VALUES(encrypted_token),
                expires_at=VALUES(expires_at),sent_at=NULL,delivery_attempts=0,created_at=NOW()');
            $save->execute([$user['id'],hash('sha256', $token),$email,hash('sha256', $user['password']),
                base64_encode($nonce . $tag . $cipher)]);
            AuditLog::create(['action' => 'Recuperación solicitada','description' => 'Solicitud de recuperación por correo.',
                'entity_type' => 'User','entity_id' => (int)$user['id'], 'ip_address' => request_ip(),
                'metadata' => json_encode(['module' => 'Autenticacion','actor' => null], JSON_THROW_ON_ERROR)]);
        });
    }

    public static function reset(string $token, string $password, string $confirmation): void
    {
        RateLimiter::consume('recovery-reset-ip:' . request_ip(), 20, 900);
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            throw new ValidationException('El enlace es inválido o ha vencido. Solicita uno nuevo.');
        }
        validate_password($password);
        if ($password !== $confirmation) {
            throw new ValidationException('Las contraseñas no coinciden.');
        }
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        transaction(function () use ($token, $newHash): void {
            $hash = hash('sha256', $token);
            $find = db()->prepare('SELECT user_id FROM password_resets WHERE token_hash=?');
            $find->execute([$hash]);
            $id = $find->fetchColumn();
            $findUser = db()->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
            $findUser->execute([$id ?: 0]);
            $user = $findUser->fetch();
            $findReset = db()->prepare('SELECT * FROM password_resets WHERE token_hash=? AND expires_at>NOW() FOR UPDATE');
            $findReset->execute([$hash]);
            $row = $findReset->fetch();
            if (
                !$row || !$user || $user['status'] !== 'active' || $row['email'] !== $user['email']
                || !hash_equals($row['password_fingerprint'], hash('sha256', $user['password']))
            ) {
                throw new ValidationException('El enlace es inválido o ha vencido. Solicita uno nuevo.');
            }
            $update = db()->prepare('UPDATE users SET password=?,session_version=session_version+1 WHERE id=?');
            $update->execute([$newHash,$user['id']]);
            db()->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$user['id']]);
            AuditService::log(
                'Autenticacion',
                'Contraseña recuperada',
                'Contraseña restablecida; sesiones anteriores revocadas.',
                'User',
                (int)$user['id'],
                actor:$user
            );
            NotificationService::send(
                (int)$user['id'],
                'system_notices',
                'Contraseña recuperada',
                'Se restableció tu contraseña. Si no lo solicitaste, contacta al administrador.',
                '/frontend/configuracion.php'
            );
        });
    }

    public static function deliver(callable $send): int
    {
        $lockName = 'devioz_reset_' . sha1((string)db()->query('SELECT DATABASE()')->fetchColumn());
        $lock = db()->prepare('SELECT GET_LOCK(?,0)');
        $lock->execute([$lockName]);
        if ((int)$lock->fetchColumn() !== 1) {
            return 0;
        }
        $sent = 0;
        try {
            $rows = db()->query('SELECT user_id,token_hash FROM password_resets
                WHERE encrypted_token IS NOT NULL AND expires_at>NOW() AND delivery_attempts<3 LIMIT 20')->fetchAll();
            foreach ($rows as $candidate) {
                try {
                    $sent += transaction(function () use ($candidate, $send): int {
                        $q = db()->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
                        $q->execute([$candidate['user_id']]);
                        $user = $q->fetch();
                        $q = db()->prepare('SELECT * FROM password_resets WHERE user_id=? AND token_hash=? AND encrypted_token IS NOT NULL AND expires_at>NOW() FOR UPDATE');
                        $q->execute([$candidate['user_id'],$candidate['token_hash']]);
                        $row = $q->fetch();
                        if (!$row || !$user) {
                            return 0;
                        }
                        if (
                            $user['status'] !== 'active' || $user['email'] !== $row['email']
                            || !hash_equals($row['password_fingerprint'], hash('sha256', $user['password']))
                        ) {
                            db()->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$user['id']]);
                            return 0;
                        }
                        $payload = base64_decode($row['encrypted_token'], true);
                        if ($payload === false) {
                            throw new RuntimeException('Cola inválida.');
                        }
                        $token = openssl_decrypt(
                            substr($payload, 28),
                            'aes-256-gcm',
                            self::key(),
                            OPENSSL_RAW_DATA,
                            substr($payload, 0, 12),
                            substr($payload, 12, 16)
                        );
                        if ($token === false || !hash_equals($row['token_hash'], hash('sha256', $token))) {
                            throw new RuntimeException('Cola inválida.');
                        }
                        $send($row['email'], $token);
                        db()->prepare('UPDATE password_resets SET encrypted_token=NULL,sent_at=NOW() WHERE user_id=?')->execute([$user['id']]);
                        return 1;
                    });
                } catch (Throwable $error) {
                    db()->prepare('UPDATE password_resets SET delivery_attempts=delivery_attempts+1 WHERE user_id=? AND token_hash=?')
                        ->execute([$candidate['user_id'],$candidate['token_hash']]);
                    error_log('DEVIOZ recovery delivery failed: ' . get_class($error));
                }
            }
            db()->exec('UPDATE password_resets SET encrypted_token=NULL WHERE expires_at<=NOW()');
        } finally {
            db()->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }
        return $sent;
    }
}
